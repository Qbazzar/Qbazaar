<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AdStatus;
use App\Enums\QueueName;
use App\Jobs\Ads\DetectDuplicateImagesJob;
use App\Models\Ad;
use App\Services\Media\BlurHashGeneratorService;
use App\Services\Media\MediaStorage;
use App\Services\Media\PerceptualHashService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Post-upload pipeline for ad images.
 *
 * The size conversions are handled by MediaLibrary (thumbnail during the
 * upload, the rest on the queue). This job adds the image metadata:
 *
 *   1. Compute a BlurHash for each image and stash it in
 *      `media.custom_properties['blurhash']`.
 *   2. Compute a 64-bit dHash (perceptual hash) and persist it to the real
 *      `media.phash` column (CHAR 16 hex) so Task 1.3 can run SQL Hamming
 *      distance queries via BIT_COUNT(CONV(a,16,10) ^ CONV(b,16,10)).
 *   3. Touch the parent ad so cache-busting / "updated_at" consumers can
 *      see the change.
 *   4. Re-run the duplicate-image check for ads already waiting for review,
 *      since their hashes may land after the ad was submitted.
 *
 * Failure handling: each image is processed inside its own try-catch so
 * a single bad file never poisons the whole batch. Errors are logged and
 * the job completes — the blurhash and phash fields stay null and the
 * resource layer handles that gracefully.
 */
class ProcessAdImagesJob implements ShouldQueue
{
    use Queueable;

    // Recomputing the hashes yields the same values, so a retry is harmless.
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $timeout = 180;

    /**
     * @param list<string> $mediaIds
     */
    public function __construct(
        public readonly array $mediaIds,
    ) {
        $this->onQueue(QueueName::MEDIA);
    }

    public function handle(
        BlurHashGeneratorService $blurHasher,
        PerceptualHashService $perceptualHasher,
        MediaStorage $storage,
    ): void {
        if ($this->mediaIds === []) {
            return;
        }

        /** @var Collection<int, Media> $mediaItems */
        $mediaItems = Media::query()->whereIn('id', $this->mediaIds)->get();

        $adIdsTouched = [];

        foreach ($mediaItems as $media) {
            try {
                $hashes = $storage->withLocalCopy($media, fn (string $path): array => [
                    'blurhash' => $blurHasher->forFile($path),
                    'phash' => $perceptualHasher->hash($path),
                ]);

                if ($hashes === null) {
                    Log::warning('ProcessAdImagesJob: media file missing', [
                        'media_id' => $media->getKey(),
                        'disk' => $media->disk,
                    ]);

                    continue;
                }

                $media->setCustomProperty('blurhash', $hashes['blurhash']);
                // Guard re-runs: a transient decode failure must not overwrite
                // a previously valid hash with null.
                $media->phash = $hashes['phash'] ?? $media->phash;
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

        if ($adIdsTouched === []) {
            return;
        }

        Ad::query()->whereIn('id', array_keys($adIdsTouched))->update([
            'updated_at' => now(),
        ]);

        Ad::query()
            ->whereIn('id', array_keys($adIdsTouched))
            ->where('status', AdStatus::PENDING->value)
            ->pluck('id')
            ->each(static fn (string $adId) => DetectDuplicateImagesJob::dispatch($adId));
    }
}
