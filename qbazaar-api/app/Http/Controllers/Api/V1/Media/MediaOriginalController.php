<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Media;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Media\MediaResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GET /api/v1/media/{media}/original — stream the original-resolution file.
 *
 * Exists purely as an expiry layer: ad images are public content, but the
 * API must not hand out permanent links to full-resolution originals
 * (hotlink / bulk-scrape protection). The `signed` middleware on the route
 * enforces the TTL baked into the URL by {@see MediaResource},
 * so this action only has to scope WHAT may be served: ad images
 * (collection `images`) whose file still exists on disk. The `sizes.*`
 * conversion URLs stay plain public URLs — only the original expires.
 *
 * Decision: a signed URL issued before an ad is taken down stays valid until
 * it expires (max 24 h); acceptable for public ad images — revisit if
 * moderation takedown must be instant.
 *
 * @group Media
 */
class MediaOriginalController extends Controller
{
    public function __invoke(Media $media): BinaryFileResponse
    {
        abort_unless($media->collection_name === 'images', 404);

        $path = $media->getPath();

        abort_unless(is_file($path), 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=3600']);
    }
}
