<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\RequestAccountDeletionAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\DeleteAccountRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * @group Account
 */
class DeleteAccountController extends Controller
{
    /**
     * Schedule the signed-in user's account for permanent deletion.
     *
     * The user is moved to `PENDING_DELETION` immediately and a delayed
     * `DeleteAccountJob` is queued for 30 days out (configurable). Signing
     * back in within the grace window cancels the request implicitly — the
     * job re-checks the status before deleting anything.
     *
     * @authenticated
     *
     * @response 202 scenario="Scheduled" {
     *   "success": true,
     *   "data": {
     *     "deletion_scheduled_at": "2026-06-22T10:00:00+03:00",
     *     "status": "pending_deletion"
     *   }
     * }
     * @response 422 scenario="Commission owed" {"success": false, "error": {"code": "ACCOUNT_003", "details": {"amount": "12.50", "currency": "QAR"}}}
     * @response 422 scenario="Order in progress" {"success": false, "error": {"code": "ACCOUNT_004", "details": {"order_id": "01J9Z3K4M5N6P7Q8R9S0T1V2W3"}}}
     */
    public function __invoke(DeleteAccountRequest $request, RequestAccountDeletionAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('requestDeletion', $user);

        if (! $user->passwordMatches((string) $request->validated('password'))) {
            throw new DomainException(ErrorCode::USER_DEACTIVATION_PASSWORD_REQUIRED);
        }

        $scheduledAt = $action->execute($user, $request->validated('reason'));

        return response()->json([
            'deletion_scheduled_at' => $scheduledAt->toIso8601String(),
            'status' => 'pending_deletion',
        ], 202);
    }
}
