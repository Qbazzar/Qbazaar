<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Promotions;

use App\Models\AdPromotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A promotion as its buyer sees it. The price is a decimal string in `currency`.
 *
 * @mixin AdPromotion
 */
class AdPromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ad_id' => $this->ad_id,
            'ad_title' => $this->whenLoaded('ad', fn (): ?string => $this->ad?->title),
            'type' => $this->type->value,
            'status' => $this->status->value,
            'payment_method' => $this->payment_method->value,
            'price' => $this->price,
            'currency' => $this->currency,
            'duration_days' => $this->duration_days,
            'transfer_reference' => $this->transfer_reference,
            'rejection_reason' => $this->rejection_reason,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
