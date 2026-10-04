<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order as either participant sees it. Amounts are decimal strings in
 * `currency`. The commission is the seller's business with the platform,
 * so only the seller sees it.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerRole = $this->viewerRole($request);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'source_id' => $this->source_id,
            'ad' => [
                'id' => $this->ad_id,
                'title' => $this->ad_title,
            ],
            'buyer_id' => $this->buyer_id,
            'seller_id' => $this->seller_id,
            'viewer_role' => $viewerRole,
            'currency' => $this->currency,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'shipping_fee' => $this->shipping_fee,
            'total' => $this->total,
            'payment_method' => $this->payment_method->value,
            'fulfillment' => $this->fulfillment?->value,
            'delivery_address' => $this->delivery_address,
            'commission' => $viewerRole === 'seller' ? [
                'rate' => $this->commission_rate,
                'amount' => $this->commission_amount,
            ] : null,
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at->toIso8601String(),
            'awaiting_handover_at' => $this->awaiting_handover_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'disputed_at' => $this->disputed_at?->toIso8601String(),
        ];
    }

    private function viewerRole(Request $request): ?string
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return match ($user->id) {
            $this->buyer_id => 'buyer',
            $this->seller_id => 'seller',
            default => null,
        };
    }
}
