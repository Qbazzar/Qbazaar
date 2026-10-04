<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Actions\Finance\RequestWithdrawalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Wallet\StoreWithdrawalRequest;
use App\Http\Resources\Api\V1\Wallet\WithdrawalResource;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The caller withdrawing their wallet balance to a bank account, and the
 * history of those requests.
 *
 * @group Wallet
 */
class WithdrawalController extends Controller
{
    /**
     * GET /api/v1/account/wallet/withdrawals — newest first, cursor paginated.
     *
     * @authenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return WithdrawalResource::collection(Withdrawal::query()
            ->ownedBy($user)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) config('qbazaar.wallet.requests_per_page')));
    }

    /**
     * POST /api/v1/account/wallet/withdrawals — the amount leaves the wallet at once and waits for review.
     *
     * @authenticated
     */
    public function store(StoreWithdrawalRequest $request, RequestWithdrawalAction $requestWithdrawal): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $withdrawal = $requestWithdrawal($user, $request->amount(), $request->bankAccountId());

        return response()->json((new WithdrawalResource($withdrawal))->resolve($request), Response::HTTP_CREATED);
    }
}
