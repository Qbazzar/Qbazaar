<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Data\Account\StepUpProof;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Services\Account\StepUpVerifier;
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
 *  - Re-prompting the very user who just confirmed the change and typed a
 *    new password to log in again on the active device is a poor UX and
 *    would surprise every modern app's users.
 *
 * The change is confirmed with the current password or, for accounts that
 * never had one, with the emailed step-up code (StepUpVerifier).
 */
class UpdatePasswordAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
        private readonly StepUpVerifier $stepUp,
    ) {}

    /**
     * @throws DomainException USER_004 for a wrong current password, ACCOUNT_001 for a wrong, expired or spent code
     */
    public function execute(User $user, StepUpProof $proof, string $newPassword, ?PersonalAccessToken $currentToken = null): void
    {
        $this->stepUp->verify($user, $proof, ErrorCode::USER_PASSWORD_CURRENT_REQUIRED);

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
