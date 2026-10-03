<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use App\Enums\LedgerAccountType;
use App\Enums\LedgerTransactionType;
use App\Services\Ledger\WalletService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query for `GET /api/v1/account/wallet/transactions`.
 *
 * @queryParam type string Only lines of this transaction type.
 * @queryParam account string `wallet` or `commission_receivable`.
 */
class ListWalletTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(LedgerTransactionType::class)],
            'account' => ['sometimes', Rule::in(array_map(fn (LedgerAccountType $type): string => $type->value, WalletService::USER_ACCOUNTS))],
        ];
    }

    public function type(): ?LedgerTransactionType
    {
        $type = $this->validated('type');

        return is_string($type) ? LedgerTransactionType::from($type) : null;
    }

    public function account(): ?LedgerAccountType
    {
        $account = $this->validated('account');

        return is_string($account) ? LedgerAccountType::from($account) : null;
    }
}
