<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Ad;
use App\Services\Media\BlurHashGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Post-upload pipeline for ad images.
 *
 * Conversions (thumbnail/medium/large/original_webp) run synchronously at
 * upload time so the HTTP response can already cite every variant. This
 * job handles the cheaper-but-still-non-trivial work:
 *
 *   1. Compute a BlurHash for each image and stash it in
 *      `media.custom_properties['blurhash']`.
 *   2. Touch the parent ad so cache-busting / "updated_at" consumers can
 *      see the change.
 *
 * Wave B will extend this job with pHash (for duplicate detection); the
 * dispatch shape stays the same so callers don't change.
 *
 * Failure handling: each image is processed inside its own try-catch so
 * a single bad file never poisons the whole batch. Errors are logged and
 * the job completes — the BlurHash field stays null and the resource layer
 * handles that gracefully.
 */
class ProcessAdImagesJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param list<string> $mediaIds
     */
    public function __construct(
        public readonly array $mediaIds,
    ) {
        $this->onQueue('low');
    }

    public function handle(BlurHashGeneratorService $blurHasher): void
    {
        if ($this->mediaIds === []) {
            return;
        }

        /** @var Collection<int, Media> $mediaItems */
        $mediaItems = Media::query()->whereIn('id', $this->mediaIds)->get();

        $adIdsTouched = [];

        foreach ($mediaItems as $media) {
            try {
                $path = $media->getPath();

                if (! is_string($path) || ! is_file($path)) {
                    Log::warning('ProcessAdImagesJob: media file missing', [
                        'media_id' => $media->getKey(),
                        'path' => $path,
                    ]);

                    continue;
                }

                $hash = $blurHasher->forFile($path);
                $media->setCustomProperty('blurhash', $hash);
                $media->save();

                if ($media->model_type === Ad::class && is_string($media->model_id)) {
                    $adIdsTouched[$media->model_id] = true;
                }
            } catch (Throwable $e) {
                Log::warning('ProcessAdImagesJob: failed to process media', [
                    'media_id' => $media->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($adIdsTouched !== []) {
            Ad::query()->whereIn('id', array_keys($adIdsTouched))->update([
                'updated_at' => now(),
            ]);
        }
    }
}
