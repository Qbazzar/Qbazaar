<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Wallet;

use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Withdrawal
 */
class WithdrawalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'currency' => 'QAR',
            'amount' => $this->amount,
            'holder_name' => $this->holder_name,
            'iban_masked' => $this->maskedIban(),
            'transfer_reference' => $this->transfer_reference,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
