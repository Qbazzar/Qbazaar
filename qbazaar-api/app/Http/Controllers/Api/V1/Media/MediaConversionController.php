<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Media;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Services\Media\MediaStorage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/v1/media/{media}/conversions/{conversion} — serve a conversion
 * that lives on the private disk (chat photos). The `signed` middleware
 * carries the expiry; files on R2 get a short presigned redirect.
 *
 * @group Media
 */
class MediaConversionController extends Controller
{
    /** Collection => conversions that may be served through this route. */
    private const SERVABLE = [
        Message::IMAGE_COLLECTION => [Message::IMAGE_PREVIEW],
    ];

    public function __invoke(Media $media, string $conversion, MediaStorage $storage): Response
    {
        abort_unless(in_array($conversion, self::SERVABLE[$media->collection_name] ?? [], true), 404);
        abort_unless($media->hasGeneratedConversion($conversion), 404);

        if (! $storage->isLocal($media)) {
            return redirect()->away($media->getTemporaryUrl(
                now()->addMinutes((int) config('qbazaar.uploads.original_redirect_ttl_minutes')),
                $conversion,
            ));
        }

        $path = $media->getPath($conversion);

        abort_unless(is_file($path), 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=3600']);
    }
}
