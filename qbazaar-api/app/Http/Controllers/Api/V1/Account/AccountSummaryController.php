<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\GetAccountSummaryAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Account\AccountSummaryResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Account
 */
class AccountSummaryController extends Controller
{
    /**
     * Dashboard counters for the signed-in user.
     *
     * Returns five at-a-glance numbers — see the OpenAPI spec for the
     * exact shape. Most counters are 0 until the corresponding module
     * ships in later sprints.
     *
     * @authenticated
     *
     * @response 200 scenario="Success" {
     *   "success": true,
     *   "data": {
     *     "my_ads": 0,
     *     "drafts": 0,
     *     "conversations": 0,
     *     "unread_notifications": 0,
     *     "favorites": 0
     *   }
     * }
     */
    public function __invoke(Request $request, GetAccountSummaryAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('view', $user);

        return response()->json(
            (new AccountSummaryResource($action->execute($user)))->toArray($request),
        );
    }
}
