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

        return [
            'id' => $this->resource->getKey(),
            'collection' => $this->resource->collection_name,
            'url' => $this->includeOriginal ? $storage->signedOriginalUrl($this->resource) : null,
            'sizes' => [
                'thumbnail' => $storage->conversionUrl($this->resource, 'thumbnail'),
                'medium' => $storage->conversionUrl($this->resource, 'medium'),
                'large' => $storage->conversionUrl($this->resource, 'large'),
                'original_webp' => $storage->conversionUrl($this->resource, 'original_webp'),
            ],
            'blurhash' => $this->resource->getCustomProperty('blurhash'),
            'width' => $this->resource->getCustomProperty('width'),
            'height' => $this->resource->getCustomProperty('height'),
            'order' => (int) ($this->resource->order_column ?? 0),
            'size_bytes' => (int) $this->resource->size,
        ];
    }
}
