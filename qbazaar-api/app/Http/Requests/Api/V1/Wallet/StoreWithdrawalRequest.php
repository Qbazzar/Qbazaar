<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use App\Http\Requests\Api\V1\Wallet\Concerns\ValidatesMoneyAmount;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam amount string The amount to withdraw, at most the available balance. Example: 250.00
 * @bodyParam bank_account_id string The account to pay into; the default account when omitted. Example: 01J0000000000000000000000
 */
class StoreWithdrawalRequest extends FormRequest
{
    use ValidatesMoneyAmount;

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
            'amount' => $this->amountRules(),
            'bank_account_id' => ['sometimes', 'nullable', 'string', 'ulid'],
        ];
    }

    public function bankAccountId(): ?string
    {
        $id = $this->validated('bank_account_id');

        return is_string($id) ? $id : null;
    }
}
