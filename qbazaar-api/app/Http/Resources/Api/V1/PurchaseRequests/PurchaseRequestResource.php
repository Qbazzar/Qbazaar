<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\PurchaseRequests;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The "Buy Now" card: endpoint responses, the `purchase_request` field of a
 * card message and every purchase_request.* broadcast. Amounts are decimal
 * strings in `currency`; `viewer_role` is null on the broadcast path.
 *
 * @mixin PurchaseRequest
 */
class PurchaseRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'ad_id' => $this->ad_id,
            'buyer_id' => $this->buyer_id,
            'seller_id' => $this->seller_id,
            'message_id' => $this->message_id,
            'order_id' => $this->order_id,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'total' => $this->resource->total(),
            'note' => $this->note,
            'cancelled_by' => $this->cancelled_by,
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'viewer_role' => $this->viewerRole($request),
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
