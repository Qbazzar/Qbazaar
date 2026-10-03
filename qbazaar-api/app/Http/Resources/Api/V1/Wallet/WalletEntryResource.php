<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Wallet;

use App\Models\LedgerEntry;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One statement line. `direction` says whether the line raised or lowered
 * the account: on the wallet an increase is money in; on the commission
 * account an increase is more debt.
 *
 * @mixin LedgerEntry
 */
class WalletEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $transaction = $this->transaction;
        $isDebit = Money::isPositive($this->debit);
        $increases = ($isDebit ? 'debit' : 'credit') === $this->account->normal_side->value;

        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'type' => $transaction->type->value,
            'account' => $this->account->type->value,
            'direction' => $increases ? 'increase' : 'decrease',
            'amount' => $isDebit ? $this->debit : $this->credit,
            'balance_after' => $this->balance_after,
            'currency' => $this->account->currency,
            'description' => __($transaction->type->descriptionKey()),
            'reference' => $transaction->reference_type === null ? null : [
                'type' => $transaction->reference_type->value,
                'id' => $transaction->reference_id,
            ],
            'posted_at' => $transaction->posted_at->toIso8601String(),
        ];
    }
}
