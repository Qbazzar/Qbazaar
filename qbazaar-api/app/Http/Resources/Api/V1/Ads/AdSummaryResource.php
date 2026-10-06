<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Ads;

use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Models\Ad;
use App\Services\Catalog\AdSpecChips;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Lean ad shape for list views — public feed, seller dashboard, browse
 * results. Skips the full description, custom fields, and the complete
 * image array so payload size stays bounded as feeds grow.
 *
 *  - `primary_image` is the first image by `order_column`, or null when
 *    the ad has none. Lists never show galleries: eager-load `primaryImage`.
 *  - `location_slug` / `category_slug` are emitted so clients can build
 *    breadcrumb / filter chips without a separate lookup.
 *  - `conversations_count` is present only where the query counted it
 *    (the seller's own list), as the "messages" stat of each card.
 *  - `summary` is the description as one plain-text line, trimmed to
 *    `qbazaar.cards.summary_length` characters.
 *  - `spec_chips` are the localised label/value pairs a card shows under the
 *    title ({@see AdSpecChips}); present only where the category is loaded.
 *  - Both stay empty for a viewer who may not open the ad (AdPolicy::view).
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
        $primary = $this->resource->relationLoaded('primaryImage')
            ? $this->resource->primaryImage
            : null;
        $showsDetails = $this->showsDetails($request);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $showsDetails ? $this->summary() : '',
            'spec_chips' => $this->whenLoaded(
                'category',
                fn (): array => $showsDetails ? app(AdSpecChips::class)->for($this->resource, app()->getLocale()) : [],
            ),
            'price' => $this->price !== null ? (float) $this->price : null,
            'price_formatted' => $this->formatPrice(),
            'price_type' => $this->price_type->value,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'is_reserved' => $this->resource->isReserved(),
            // The strongest active paid promotion (highlight, push_up, gallery, premium), or null.
            'promotion' => $this->resource->promotionType()?->value,
            'ad_type' => $this->ad_type->value,
            'shipping' => $this->shipping->value,
            'views_count' => (int) $this->views_count,
            'favorites_count' => (int) $this->favorites_count,
            // Set by ViewerFavorites for a signed-in viewer; guests always get false.
            'is_favorited' => (bool) $this->resource->getAttribute('is_favorited'),
            'conversations_count' => $this->whenCounted('conversations'),
            'primary_image' => $primary instanceof Media
                ? (new MediaResource($primary))->withoutOriginal()->toArray($request)
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
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * Favorites, recently viewed and chats keep ads that went back to review
     * or were hidden since, and their description and field values may not
     * be approved. Listed ads skip the policy so a page of cards does not
     * resolve the viewer once per card.
     */
    private function showsDetails(Request $request): bool
    {
        return $this->resource->isPubliclyListed()
            || Gate::forUser($request->user('sanctum'))->allows('view', $this->resource);
    }

    /**
     * Cut at a word. Nothing is stripped: the description is plain text, so a
     * "<" in it is part of what the seller wrote.
     */
    private function summary(): string
    {
        $text = Str::squish((string) $this->description);
        $limit = (int) config('qbazaar.cards.summary_length');

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit + 1);

        return mb_substr($cut, 0, mb_strrpos($cut, ' ') ?: $limit) . '…';
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
