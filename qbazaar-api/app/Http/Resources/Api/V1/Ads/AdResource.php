<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Ads;

use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Http\Resources\Api\V1\Reference\CategoryResource;
use App\Http\Resources\Api\V1\Reference\LocationResource;
use App\Http\Resources\Api\V1\Users\PublicUserResource;
use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Full ad payload — used by `show`, `store`, `update`, `publish`,
 * `mark-sold`, `renew`. Lighter list views go through {@see AdSummaryResource}.
 *
 * Conditional includes:
 *  - `user`, `category`, `location` — present only when eager-loaded by the
 *    caller. Saves a round-trip on the seller dashboard where we already
 *    know the user.
 *  - `images` — always returned when the media relation has been loaded,
 *    ordered by `order_column` so the frontend doesn't have to sort.
 *
 * @mixin Ad
 */
class AdResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price !== null ? (float) $this->price : null,
            'price_type' => $this->price_type->value,
            'currency' => $this->currency,
            'condition' => $this->condition?->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'custom_fields' => $this->custom_fields,
            'views_count' => (int) $this->views_count,
            'favorites_count' => (int) $this->favorites_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            'user' => $this->whenLoaded(
                'user',
                fn () => (new PublicUserResource($this->resource->user))->toArray($request),
            ),
            'category' => $this->whenLoaded(
                'category',
                fn () => (new CategoryResource($this->resource->category))->toArray($request),
            ),
            'location' => $this->whenLoaded(
                'location',
                fn () => (new LocationResource($this->resource->location))->toArray($request),
            ),

            'images' => $this->whenLoaded('media', fn () => $this->resource->getMedia('images')
                ->sortBy('order_column')
                ->values()
                ->map(fn (Media $m): array => (new MediaResource($m))->toArray($request))
                ->all()),
        ];
    }
}
