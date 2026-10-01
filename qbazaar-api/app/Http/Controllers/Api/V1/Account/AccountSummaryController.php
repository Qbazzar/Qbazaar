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
     * Dashboard counters for the signed-in user. `my_ads` counts every
     * non-draft listing; `ads_by_status` feeds the My Ads tab badges.
     *
     * @authenticated
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
