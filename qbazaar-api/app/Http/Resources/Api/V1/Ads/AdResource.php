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
 *  - `street` — null for everyone but the seller unless `show_full_address`.
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
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price !== null ? (float) $this->price : null,
            'price_type' => $this->price_type->value,
            'currency' => $this->currency,
            'condition' => $this->condition?->value,
            'ad_type' => $this->ad_type->value,
            'shipping' => $this->shipping->value,
            'shipping_fee' => $this->shipping_fee,
            'quantity' => $this->quantity,
            'postal_code' => $this->postal_code,
            'street' => $this->streetFor($request),
            'show_full_address' => $this->show_full_address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_reserved' => $this->resource->isReserved(),
            'reserved_at' => $this->reserved_at?->toIso8601String(),
            'custom_fields' => $this->custom_fields,
            'views_count' => (int) $this->views_count,
            'favorites_count' => (int) $this->favorites_count,
            // Set by ViewerFavorites for a signed-in viewer; guests always get false.
            'is_favorited' => (bool) $this->resource->getAttribute('is_favorited'),
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

    /** By default only the postal code and city are public; the street needs the seller's opt-in. */
    private function streetFor(Request $request): ?string
    {
        if ($this->show_full_address || $request->user('sanctum')?->getAuthIdentifier() === $this->user_id) {
            return $this->street;
        }

        return null;
    }
}
