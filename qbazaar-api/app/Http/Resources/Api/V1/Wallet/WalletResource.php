<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Wallet;

use App\Data\Ledger\WalletSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property WalletSummary $resource
 */
class WalletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'currency' => 'QAR',
            'available_balance' => $this->resource->available,
            'withdrawable_balance' => $this->resource->withdrawable,
            'commission_debt' => $this->resource->commissionDebt,
            'debt_ceiling' => $this->resource->debtCeiling,
            'can_accept_orders' => $this->resource->canAcceptOrders,
        ];
    }
}
