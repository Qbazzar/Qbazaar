<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Data\Account\StepUpProof;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;

/**
 * Checks the step-up proof before a password change or an account deletion.
 *
 * Passwordless accounts can only confirm with the emailed code, so the code
 * path goes through the same ReauthCodeService as the email and phone
 * changes: same attempt limit, same single use, same ACCOUNT_001 error.
 */
class StepUpVerifier
{
    public function __construct(private readonly ReauthCodeService $reauthCodes) {}

    /**
     * @param ErrorCode $wrongPassword the endpoint's existing error for a wrong password
     *
     * @throws DomainException
     */
    public function verify(User $user, StepUpProof $proof, ErrorCode $wrongPassword): void
    {
        if ($proof->reauthCode !== null) {
            $this->reauthCodes->consume($user, $proof->reauthCode);

            return;
        }

        if (! $user->passwordMatches((string) $proof->password)) {
            throw new DomainException($wrongPassword);
        }
    }
}
