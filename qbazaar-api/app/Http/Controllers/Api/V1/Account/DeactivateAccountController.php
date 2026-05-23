<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\DeactivateAccountAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\DeactivateAccountRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * @group Account
 */
class DeactivateAccountController extends Controller
{
    /**
     * Soft-disable the signed-in user's account.
     *
     * Verifies the password (USER_005 on mismatch), then dispatches the
     * action which flips `status → DEACTIVATED` and burns every refresh
     * token + Sanctum PAT. The user can self-reactivate by logging in again
     * within the grace window (the Login flow handles that path).
     *
     * @authenticated
     *
     * @response 204 scenario="Deactivated" {}
     */
    public function __invoke(DeactivateAccountRequest $request, DeactivateAccountAction $action): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('deactivate', $user);

        if (! Hash::check((string) $request->validated('password'), $user->password)) {
            throw new DomainException(ErrorCode::USER_DEACTIVATION_PASSWORD_REQUIRED);
        }

        $action->execute($user, $request->validated('reason'));

        return response()->noContent();
    }
}
