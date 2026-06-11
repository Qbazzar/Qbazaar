<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Media;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Wire shape for a single Spatie Media row attached to an Ad (or any other
 * model). Keeps the URL set + metadata stable across endpoints.
 *
 *  - `url` is a temporary signed link to the original-resolution file
 *    (route api.v1.media.original); it expires after
 *    qbazaar.uploads.original_url_ttl_hours so originals can't be
 *    hotlinked permanently. Clients must not persist it.
 *  - `sizes` always carries the four conversion keys as plain public URLs.
 *    If a conversion hasn't generated yet (job lag), we fall back to the
 *    original URL so the frontend never sees an empty string.
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
            'url' => $this->signedOriginalUrl(),
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
     * Expiring signed URL for the original-resolution file. Served through
     * MediaOriginalController (`signed` middleware) rather than a permanent
     * storage URL so full-res originals can't be hotlinked forever.
     */
    private function signedOriginalUrl(): string
    {
        return URL::temporarySignedRoute(
            'api.v1.media.original',
            now()->addHours((int) config('qbazaar.uploads.original_url_ttl_hours')),
            ['media' => $this->resource->getKey()],
        );
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
