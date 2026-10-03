<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Actions\Finance\SubmitSettlementAction;
use App\Enums\SettlementMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Wallet\StoreSettlementRequest;
use App\Http\Resources\Api\V1\Wallet\SettlementResource;
use App\Models\CommissionSettlement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The caller paying off their commission debt, and the history of those
 * payments with their review status.
 *
 * @group Wallet
 */
class SettlementController extends Controller
{
    /**
     * GET /api/v1/account/wallet/settlements — newest first, cursor paginated.
     *
     * @authenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return SettlementResource::collection(CommissionSettlement::query()
            ->ownedBy($user)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) config('qbazaar.wallet.requests_per_page')));
    }

    /**
     * POST /api/v1/account/wallet/settlements — a bank transfer (pending review) or netting from the wallet (applied at once).
     *
     * @authenticated
     */
    public function store(StoreSettlementRequest $request, SubmitSettlementAction $submit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $settlement = $request->settlementMethod() === SettlementMethod::WALLET
            ? $submit->fromWallet($user, $request->amount())
            : $submit->byBankTransfer($user, $request->amount(), $request->bankReference(), $request->proof());

        return response()->json((new SettlementResource($settlement))->resolve($request), Response::HTTP_CREATED);
    }
}
