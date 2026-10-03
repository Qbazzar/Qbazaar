<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Account;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payout account. The IBAN is only ever returned masked.
 *
 * @mixin BankAccount
 */
class BankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'holder_name' => $this->holder_name,
            'iban_masked' => $this->maskedIban(),
            'bank_name' => $this->bank_name,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
