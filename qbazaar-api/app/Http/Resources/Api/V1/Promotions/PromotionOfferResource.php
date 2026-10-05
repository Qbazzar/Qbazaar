<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Promotions;

use App\Data\Promotions\PromotionOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PromotionOffer $resource
 */
class PromotionOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->resource->type->value,
            'price' => $this->resource->price,
            'currency' => $this->resource->currency,
            'duration_days' => $this->resource->durationDays,
        ];
    }
}
