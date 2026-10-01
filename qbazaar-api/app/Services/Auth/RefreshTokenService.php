<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Centralises every operation on the refresh-token table.
 *
 * Why a service?
 *  - Token rotation is conceptually atomic ("mark old used, mint new, return both")
 *    and must be guarded against double-use races — so we want one place that
 *    owns the DB transaction + Redis lock.
 *  - Reuse: register, login, and refresh all need to mint pairs.
 *  - Testability: controllers stay thin and easy to mock.
 */
class RefreshTokenService
{
    public function __construct(private readonly RefreshTokenHasher $hasher) {}

    /**
     * Issue a brand-new access+refresh token pair for the given user.
     * Used by register and login. Does NOT rotate anything; just mints.
     *
     * The optional $ip / $deviceLabel parameters get persisted onto the
     * matching personal_access_tokens row so the GET /account/sessions
     * endpoint can render a useful "this is your Chrome / 1.2.3.4" line
     * per session.
     */
    public function issue(User $user, ?string $deviceFingerprint = null, ?string $ip = null, ?string $deviceLabel = null): TokenPair
    {
        $accessTtlMinutes = (int) config('qbazaar.auth.access_token_ttl_minutes', 15);
        $refreshTtlDays = (int) config('qbazaar.auth.refresh_token_ttl_days', 30);

        $newAccessToken = $user->createToken(
            name: 'api',
            abilities: ['*'],
            expiresAt: Carbon::now()->addMinutes($accessTtlMinutes),
        );

        if ($ip !== null || $deviceLabel !== null) {
            $newAccessToken->accessToken->forceFill(array_filter([
                'ip_address' => $ip,
                'device_label' => $deviceLabel,
            ], fn ($v) => $v !== null))->save();
        }

        $accessToken = $newAccessToken->plainTextToken;

        // HasUlids::newUniqueId() returns lowercase ULIDs — match that convention
        // so any subsequent lookup-by-id (in findCandidate) stays consistent.
        $rowId = strtolower((string) Str::ulid());
        $rawRefresh = $this->generateRawRefreshToken($rowId);

        // We assign the id explicitly (instead of letting HasUlids mint one)
        // so the raw token can embed the row id. `id` isn't in $fillable on
        // purpose, so we use a model instance + forceFill instead of ::create.
        $token = new RefreshToken;
        $token->id = $rowId;
        $token->forceFill([
            'user_id' => $user->id,
            'personal_access_token_id' => $newAccessToken->accessToken->getKey(),
            'token_hash' => $this->hasher->hash($rawRefresh),
            'device_fingerprint' => $deviceFingerprint,
            'expires_at' => Carbon::now()->addDays($refreshTtlDays),
        ])->save();

        return new TokenPair(
            accessToken: $accessToken,
            refreshToken: $rawRefresh,
            expiresIn: $accessTtlMinutes * 60,
        );
    }

    /**
     * Rotate a presented refresh token.
     *
     * Algorithm:
     *  1. Look the row up by the ULID embedded in the token and verify its hash.
     *  2. Take a short Redis lock keyed on the matched row to serialise
     *     concurrent refreshes from the same client.
     *  3. In a DB transaction:
     *      - If the matched row is already `used_at` set → REPLAY DETECTED.
     *        Burn every refresh token for that user and abort.
     *      - If expired → abort with AUTH_TOKEN_EXPIRED.
     *      - If the owner can no longer sign in → abort with AUTH_002.
     *      - Otherwise mark it used, retire its access token and mint a new pair.
     *
     * Throws DomainException with the right ErrorCode on every failure so the
     * global exception handler in bootstrap/app.php shapes the response.
     *
     * @return array{user: User, tokens: TokenPair}
     */
    public function rotate(string $presentedRaw, ?string $deviceFingerprint = null, ?string $ip = null, ?string $deviceLabel = null): array
    {
        $candidate = $this->findCandidate($presentedRaw);

        if ($candidate === null) {
            $this->fail(ErrorCode::AUTH_TOKEN_INVALID);
        }

        $lock = Cache::lock('refresh:' . $candidate->id, 5);

        if (! $lock->get()) {
            // Another request is already rotating this token — treat as replay-ish.
            $this->fail(ErrorCode::AUTH_TOKEN_INVALID);
        }

        try {
            // We split the "burn the family" path out of the transaction so the
            // bulk update can commit even when we then throw AUTH_TOKEN_INVALID
            // to the client. A rolled-back replay-detection would leave the
            // tokens live, defeating the whole point.
            [$replay, $expired, $userId] = $this->inspectCandidate($candidate);

            if ($replay) {
                RefreshToken::query()
                    ->where('user_id', $userId)
                    ->whereNull('used_at')
                    ->update(['used_at' => Carbon::now()]);

                $this->fail(ErrorCode::AUTH_TOKEN_INVALID);
            }

            if ($expired) {
                $this->fail(ErrorCode::AUTH_TOKEN_EXPIRED);
            }

            /** @var User $user */
            $user = $candidate->user()->firstOrFail();

            if (! $user->status->canLogin()) {
                $this->fail(ErrorCode::AUTH_ACCOUNT_SUSPENDED);
            }

            return DB::transaction(function () use ($candidate, $user, $deviceFingerprint, $ip, $deviceLabel): array {
                /** @var RefreshToken $fresh */
                $fresh = RefreshToken::query()->lockForUpdate()->findOrFail($candidate->id);

                // Re-check inside the lock to defeat concurrent rotations of
                // the same token. The branches above ran outside the lock so
                // we need to re-verify before mutating.
                if ($fresh->isUsed() || $fresh->isExpired()) {
                    $this->fail(ErrorCode::AUTH_TOKEN_INVALID);
                }

                $fresh->forceFill(['used_at' => Carbon::now()])->save();

                // The session moves to the new access token. Leaving the old one
                // alive would list the device twice, and revoking that stale row
                // would not reach the refresh token that keeps the device in.
                PersonalAccessToken::query()->whereKey($fresh->personal_access_token_id)->delete();

                return [
                    'user' => $user,
                    'tokens' => $this->issue($user, $deviceFingerprint, $ip, $deviceLabel),
                ];
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Cheap, read-only inspection of a refresh-token row so we can decide
     * whether we're in the happy path, the replay path, or the expired path
     * before we open the rotation transaction.
     *
     * @return array{0: bool, 1: bool, 2: string} [replay, expired, userId]
     */
    private function inspectCandidate(RefreshToken $candidate): array
    {
        return [$candidate->isUsed(), $candidate->isExpired(), (string) $candidate->user_id];
    }

    /**
     * Revoke a refresh token if the caller can present it. Best-effort: silent
     * on miss so logout doesn't leak whether a token was active. Deleted rather
     * than marked used, for the reason given on revokeSession().
     */
    public function revoke(string $presentedRaw): void
    {
        $row = $this->findCandidate($presentedRaw);

        if ($row !== null && ! $row->isUsed()) {
            $row->delete();
        }
    }

    /**
     * End one session: the access token and every refresh token minted with it.
     *
     * Revoked refresh tokens are deleted rather than marked used: a used token
     * that comes back is treated as a replay and burns every session the user
     * has, so a signed-out device retrying its refresh would log the user out
     * of all their other devices.
     */
    public function revokeSession(PersonalAccessToken $accessToken): void
    {
        DB::transaction(function () use ($accessToken): void {
            RefreshToken::query()
                ->where('personal_access_token_id', $accessToken->getKey())
                ->whereNull('used_at')
                ->delete();

            $accessToken->delete();
        });
    }

    /**
     * Sign the user out everywhere: burns every refresh token and deletes
     * every access token.
     */
    public function revokeAllSessions(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->burnAllForUser($user);
            $user->tokens()->delete();
        });
    }

    /**
     * Force every still-active refresh token for the given user into the
     * "used" state in one update. Used by sensitive actions such as
     * password reset, where the security policy is "log them out everywhere".
     *
     * @return int number of rows burnt
     */
    public function burnAllForUser(User $user): int
    {
        return RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => Carbon::now()]);
    }

    /**
     * Looks up the candidate row for a presented raw refresh token.
     *
     * We embed the row ID (a ULID) in the raw token itself so we can do a single
     * primary-key read; the hash is still verified before we trust the row.
     */
    private function findCandidate(string $presentedRaw): ?RefreshToken
    {
        $rowId = $this->extractRowId($presentedRaw);
        if ($rowId === null) {
            return null;
        }

        /** @var RefreshToken|null $row */
        $row = RefreshToken::query()->find($rowId);

        if ($row === null) {
            return null;
        }

        return $this->hasher->check($presentedRaw, $row->token_hash) ? $row : null;
    }

    private function extractRowId(string $presentedRaw): ?string
    {
        // Raw format: rt_<ULID-26-base32><random>
        if (! str_starts_with($presentedRaw, 'rt_')) {
            return null;
        }

        $candidate = substr($presentedRaw, 3, 26);
        if (strlen($candidate) !== 26) {
            return null;
        }

        // Normalise case to match the lowercase ULID convention used by
        // HasUlids::newUniqueId — so a client that round-tripped the token
        // through an upper/lower-cased channel still resolves.
        return strtolower($candidate);
    }

    /**
     * Mint a raw refresh token whose first 26 chars after the `rt_` prefix is
     * a ULID — letting us look the row up directly without scanning.
     */
    private function generateRawRefreshToken(string $rowId): string
    {
        return 'rt_' . $rowId . Str::random(16);
    }

    /**
     * @throws DomainException
     */
    private function fail(ErrorCode $code): never
    {
        throw new DomainException($code);
    }
}
