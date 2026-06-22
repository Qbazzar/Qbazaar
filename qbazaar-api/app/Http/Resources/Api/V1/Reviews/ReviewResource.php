<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Reviews;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at->toIso8601String(),
            'reviewer' => $this->whenLoaded('reviewer', fn (): array => [
                'id' => $this->resource->reviewer->id,
                'full_name' => $this->resource->reviewer->full_name,
                'avatar_url' => $this->resource->reviewer->avatar_url,
            ]),
            'ad' => $this->whenLoaded('ad', fn (): array => [
                'id' => $this->resource->ad->id,
                'title' => $this->resource->ad->title,
            ]),
        ];
    }
}
