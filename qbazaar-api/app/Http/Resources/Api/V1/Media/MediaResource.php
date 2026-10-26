<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Media;

use App\Services\Media\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Wire shape for a single Spatie Media row attached to an Ad (or any other
 * model). Keeps the URL set + metadata stable across endpoints.
 *
 *  - `url` is a temporary signed link to the original-resolution file
 *    (route api.v1.media.original); it expires after
 *    qbazaar.uploads.original_url_ttl_hours, rounded up to the hour, so
 *    originals can't be hotlinked permanently. Clients must not persist it.
 *    Listings get null ({@see withoutOriginal()}): a card never shows the
 *    original, and a payload without per-request signatures caches well.
 *  - `sizes` always carries the four conversion keys as plain public URLs.
 *    If a conversion hasn't generated yet (job lag), we fall back to the
 *    signed original URL so the frontend never sees an empty string.
 *  - `blurhash` is null while ProcessAdImagesJob is queued; clients should
 *    treat it as optional metadata.
 *  - `width` / `height` are populated by the same job; null in the meantime.
 *
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    private bool $includeOriginal = true;

    public function withoutOriginal(): static
    {
        $this->includeOriginal = false;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $storage = app(MediaStorage::class);
        $sizes = $this->sizes($storage);

        return [
            'id' => $this->resource->getKey(),
            'collection' => $this->resource->collection_name,
            'url' => $this->includeOriginal ? $storage->signedOriginalUrl($this->resource) : null,
            'sizes' => $sizes,
            'blurhash' => $this->resource->getCustomProperty('blurhash'),
            'width' => $this->resource->getCustomProperty('width'),
            'height' => $this->resource->getCustomProperty('height'),
            'order' => (int) ($this->resource->order_column ?? 0),
            'size_bytes' => (int) $this->resource->size,
        ];
    }

    /**
     * The web picks between `medium` and `large` by width and may name one
     * from the other's URL, so both CDN files must exist or neither is
     * offered: with one still queued (or failed), both fall back to the
     * signed original.
     *
     * @return array<string, string>
     */
    private function sizes(MediaStorage $storage): array
    {
        $sizes = [];
        foreach (['thumbnail', 'medium', 'large', 'original_webp'] as $conversion) {
            $sizes[$conversion] = $storage->conversionUrl($this->resource, $conversion);
        }

        $pairReady = $this->resource->hasGeneratedConversion('medium') && $this->resource->hasGeneratedConversion('large');
        if (! $pairReady) {
            $original = $storage->signedOriginalUrl($this->resource);
            $sizes['medium'] = $original;
            $sizes['large'] = $original;
        }

        return $sizes;
    }
}
