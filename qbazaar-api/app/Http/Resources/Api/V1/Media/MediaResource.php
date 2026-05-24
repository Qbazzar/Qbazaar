<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Media;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Wire shape for a single Spatie Media row attached to an Ad (or any other
 * model). Keeps the URL set + metadata stable across endpoints.
 *
 *  - `sizes` always carries the four conversion keys. If a conversion
 *    hasn't generated yet (job lag), we fall back to the original URL so
 *    the frontend never sees an empty string.
 *  - `blurhash` is null while ProcessAdImagesJob is queued; clients should
 *    treat it as optional metadata.
 *  - `width` / `height` are populated by the same job; null in the meantime.
 *
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'collection' => $this->resource->collection_name,
            'url' => $this->resource->getUrl(),
            'sizes' => [
                'thumbnail' => $this->urlForConversion('thumbnail'),
                'medium' => $this->urlForConversion('medium'),
                'large' => $this->urlForConversion('large'),
                'original_webp' => $this->urlForConversion('original_webp'),
            ],
            'blurhash' => $this->resource->getCustomProperty('blurhash'),
            'width' => $this->resource->getCustomProperty('width'),
            'height' => $this->resource->getCustomProperty('height'),
            'order' => (int) ($this->resource->order_column ?? 0),
            'size_bytes' => (int) $this->resource->size,
        ];
    }

    /**
     * Conversion URL with graceful fallback to the original when the
     * conversion hasn't been generated yet. Keeps the response shape
     * always-populated so client code can avoid null-checks per size.
     */
    private function urlForConversion(string $name): string
    {
        if ($this->resource->hasGeneratedConversion($name)) {
            return $this->resource->getUrl($name);
        }

        return $this->resource->getUrl();
    }
}
