<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Wallet\ListWalletTransactionsRequest;
use App\Http\Resources\Api\V1\Wallet\WalletEntryResource;
use App\Http\Resources\Api\V1\Wallet\WalletResource;
use App\Models\User;
use App\Services\Ledger\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The caller's wallet: balances read from the ledger, and the statement of
 * every line posted to their accounts. Read-only; money moves only through
 * order, settlement, withdrawal and promotion flows.
 *
 * @group Wallet
 */
class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
    ) {}

    /**
     * GET /api/v1/account/wallet
     *
     * @authenticated
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json((new WalletResource($this->wallets->summary($user->id)))->resolve($request));
    }

    /**
     * GET /api/v1/account/wallet/transactions — newest first, cursor paginated.
     *
     * @authenticated
     */
    public function transactions(ListWalletTransactionsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return WalletEntryResource::collection($this->wallets->statement(
            $user->id,
            $request->type(),
            $request->account(),
            (int) config('qbazaar.wallet.statement_per_page'),
        ));
    }
}
