<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Notifications\SecurityAlertNotification;
use App\Services\Auth\DeviceFingerprintService;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\TokenPair;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Authenticates a user by either email OR Qatari phone, then mints a fresh
 * access+refresh pair. Why not Auth::attempt()? Because we have a single
 * `identifier` field on the wire and Hash::check + manual lookup is cleaner
 * than building dynamic credentials arrays.
 *
 * New-device alerting:
 *   When a successful login comes from a fingerprint we haven't seen for this
 *   user before, we fire SecurityAlertNotification. The "have we seen it"
 *   lookup runs BEFORE we mint the new refresh token (otherwise the brand-new
 *   row would always make the fingerprint look "known"). We also skip the
 *   alert on a user's very first login — no previous fingerprints = nothing
 *   to compare against.
 */
class LoginUserAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
        private readonly DeviceFingerprintService $fingerprintService,
    ) {}

    /**
     * @return array{user: User, tokens: TokenPair}
     *
     * @throws DomainException
     */
    public function execute(
        string $identifier,
        string $password,
        ?string $deviceFingerprint = null,
        ?string $deviceLabel = null,
        ?string $ip = null,
    ): array {
        $user = $this->lookup($identifier);

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new DomainException(ErrorCode::AUTH_INVALID_CREDENTIALS);
        }

        if ($user->status === UserStatus::SUSPENDED) {
            throw new DomainException(ErrorCode::AUTH_ACCOUNT_SUSPENDED);
        }

        $isFirstLogin = $user->last_login_at === null;
        $isNewDevice = $deviceFingerprint !== null
            && ! $isFirstLogin
            && ! $this->fingerprintService->isKnownForUser($user, $deviceFingerprint);

        $user->forceFill(['last_login_at' => Carbon::now()])->save();

        $tokens = $this->refreshTokens->issue($user, $deviceFingerprint, $ip, $deviceLabel);

        if ($isNewDevice) {
            $user->notify(new SecurityAlertNotification(
                deviceLabel: $deviceLabel ?? 'unknown',
                ip: $ip ?? 'unknown',
                occurredAt: Carbon::now(),
            ));
        }

        return ['user' => $user, 'tokens' => $tokens];
    }

    /**
     * Resolve identifier → email or phone lookup.
     * Phone numbers must be presented in the canonical +974XXXXXXXX shape.
     */
    private function lookup(string $identifier): ?User
    {
        $column = str_starts_with($identifier, '+') ? 'phone' : 'email';
        $value = $column === 'email' ? strtolower($identifier) : $identifier;

        /** @var User|null $user */
        $user = User::query()->where($column, $value)->first();

        return $user;
    }
}
