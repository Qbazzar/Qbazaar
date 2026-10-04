<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\StoreBankAccountRequest;
use App\Http\Resources\Api\V1\Account\BankAccountResource;
use App\Models\User;
use App\Services\Finance\BankAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The signed-in user's payout accounts for withdrawals. The list is bounded
 * by `qbazaar.wallet.max_bank_accounts`, so it is returned without paging.
 *
 * @group Wallet
 */
class BankAccountController extends Controller
{
    public function __construct(private readonly BankAccountService $bankAccounts) {}

    /**
     * GET /api/v1/account/bank-accounts — the default first.
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(BankAccountResource::collection($this->bankAccounts->list($this->user($request)))->resolve($request));
    }

    /**
     * POST /api/v1/account/bank-accounts — the first account always becomes the default.
     *
     * @authenticated
     */
    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        $account = $this->bankAccounts->create(
            $this->user($request),
            $request->holderName(),
            $request->iban(),
            $request->bankName(),
            $request->wantsDefault(),
        );

        return response()->json((new BankAccountResource($account))->resolve($request), Response::HTTP_CREATED);
    }

    /**
     * DELETE /api/v1/account/bank-accounts/{id} — deleting the default promotes the newest remaining one.
     *
     * @authenticated
     */
    public function destroy(Request $request, string $id): Response
    {
        $this->bankAccounts->delete($this->user($request), $id);

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
