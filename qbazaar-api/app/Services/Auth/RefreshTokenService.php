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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
    /**
     * Issue a brand-new access+refresh token pair for the given user.
     * Used by register and login. Does NOT rotate anything; just mints.
     */
    public function issue(User $user, ?string $deviceFingerprint = null): TokenPair
    {
        $accessTtlMinutes = (int) config('qbazaar.auth.access_token_ttl_minutes', 15);
        $refreshTtlDays = (int) config('qbazaar.auth.refresh_token_ttl_days', 30);

        $accessToken = $user->createToken(
            name: 'api',
            abilities: ['*'],
            expiresAt: Carbon::now()->addMinutes($accessTtlMinutes),
        )->plainTextToken;

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
            'token_hash' => Hash::make($rawRefresh),
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
     *  1. Lookup candidate rows for the user space (we can't query by hash directly
     *     because Hash::make uses a random salt; we have to verify in PHP).
     *  2. Take a short Redis lock keyed on the matched row to serialise
     *     concurrent refreshes from the same client.
     *  3. In a DB transaction:
     *      - If the matched row is already `used_at` set → REPLAY DETECTED.
     *        Burn every refresh token for that user and abort.
     *      - If expired → abort with AUTH_TOKEN_EXPIRED.
     *      - Otherwise mark it used and mint a new pair.
     *
     * Throws DomainException with the right ErrorCode on every failure so the
     * global exception handler in bootstrap/app.php shapes the response.
     *
     * @return array{user: User, tokens: TokenPair}
     */
    public function rotate(string $presentedRaw, ?string $deviceFingerprint = null): array
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

            return DB::transaction(function () use ($candidate, $deviceFingerprint): array {
                /** @var RefreshToken $fresh */
                $fresh = RefreshToken::query()->lockForUpdate()->findOrFail($candidate->id);

                // Re-check inside the lock to defeat concurrent rotations of
                // the same token. The branches above ran outside the lock so
                // we need to re-verify before mutating.
                if ($fresh->isUsed() || $fresh->isExpired()) {
                    $this->fail(ErrorCode::AUTH_TOKEN_INVALID);
                }

                $fresh->forceFill(['used_at' => Carbon::now()])->save();

                /** @var User $user */
                $user = $fresh->user()->firstOrFail();

                return [
                    'user' => $user,
                    'tokens' => $this->issue($user, $deviceFingerprint),
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
     * on miss so logout doesn't leak whether a token was active.
     */
    public function revoke(string $presentedRaw): void
    {
        $row = $this->findCandidate($presentedRaw);

        if ($row !== null && ! $row->isUsed()) {
            $row->forceFill(['used_at' => Carbon::now()])->save();
        }
    }

    /**
     * Looks up the candidate row for a presented raw refresh token.
     *
     * We embed the row ID (a ULID) in the raw token itself so we can do a single
     * indexed read instead of an O(n) Hash::check scan. The salted hash is still
     * verified before we trust the row.
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

        return Hash::check($presentedRaw, $row->token_hash) ? $row : null;
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
