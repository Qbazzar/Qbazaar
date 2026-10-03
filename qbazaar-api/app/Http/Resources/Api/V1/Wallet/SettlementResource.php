<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Wallet;

use App\Models\CommissionSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommissionSettlement
 */
class SettlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method->value,
            'status' => $this->status->value,
            'currency' => 'QAR',
            'amount' => $this->amount,
            'bank_reference' => $this->bank_reference,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
