<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Ads;

use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Lean ad shape for list views — public feed, seller dashboard, browse
 * results. Skips the full description, custom fields, and the complete
 * image array so payload size stays bounded as feeds grow.
 *
 *  - `primary_image` is the first image by `order_column`, or null when
 *    the ad has none. Lists never show galleries.
 *  - `location_slug` / `category_slug` are emitted so clients can build
 *    breadcrumb / filter chips without a separate lookup.
 *  - `price_formatted` carries the localised display string ("1,200 ر.ق")
 *    while `price` keeps the numeric value for client-side sorting.
 *
 * @mixin Ad
 */
class AdSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primary = $this->resource->relationLoaded('media')
            ? $this->resource->getMedia('images')->sortBy('order_column')->first()
            : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'price' => $this->price !== null ? (float) $this->price : null,
            'price_formatted' => $this->formatPrice(),
            'price_type' => $this->price_type->value,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'views_count' => (int) $this->views_count,
            'favorites_count' => (int) $this->favorites_count,
            'primary_image' => $primary instanceof Media
                ? (new MediaResource($primary))->toArray($request)
                : null,
            'category_slug' => $this->whenLoaded(
                'category',
                fn (): ?string => $this->resource->category?->slug,
            ),
            'location_slug' => $this->whenLoaded(
                'location',
                fn (): ?string => $this->resource->location?->slug,
            ),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * Render the price for the active locale. Uses `number_format` rather
     * than `NumberFormatter` because the latter pulls intl and Qatar uses
     * a single currency — a thin formatter is enough.
     */
    private function formatPrice(): ?string
    {
        if ($this->price === null) {
            return null;
        }

        return number_format((float) $this->price, 2, '.', ',') . ' ' . $this->currency;
    }
}
