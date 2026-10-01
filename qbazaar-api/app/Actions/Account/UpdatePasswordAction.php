<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Changes a user's password, then forces a re-login on every device EXCEPT
 * the one performing the change.
 *
 * Why burn other sessions?
 *  - A password change is the standard "I think my account was compromised"
 *    self-remediation. Leaving every other session signed in would defeat the
 *    purpose; users expect the change to invalidate everything they didn't
 *    explicitly authorise.
 *
 * Why keep the CURRENT session alive?
 *  - Re-prompting the very user who just typed their old + new password
 *    to log in again on the active device is a poor UX and would surprise
 *    every modern app's users.
 */
class UpdatePasswordAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    /**
     * @throws DomainException
     */
    public function execute(User $user, string $currentPassword, string $newPassword, ?PersonalAccessToken $currentToken = null): void
    {
        if (! $user->passwordMatches($currentPassword)) {
            throw new DomainException(ErrorCode::USER_PASSWORD_CURRENT_REQUIRED);
        }

        // Mutate password — the 'hashed' cast on User does the hashing for us.
        // Using forceFill so this works regardless of $fillable hygiene.
        $user->forceFill(['password' => $newPassword])->save();

        // Burn every refresh token (including the current device's — we'll
        // mint a new one on next login if needed; the access token below
        // is what keeps the current device authenticated until it expires).
        $this->refreshTokens->burnAllForUser($user);

        // Burn every Sanctum personal access token EXCEPT the current one.
        $currentId = $currentToken?->getKey();

        $query = $user->tokens();
        if ($currentId !== null) {
            $query->whereKeyNot($currentId);
        }
        $query->delete();
    }
}
