<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\QueueName;
use App\Jobs\Ads\ModerateAdJob;
use App\Models\Ad;
use App\Services\Media\AdImageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Post-upload pipeline for ad images. MediaLibrary renders the size
 * conversions in its own queued jobs; this one runs {@see AdImageProcessor}
 * on each new photo and touches the parent ads so cache consumers see the
 * change. AttachAdImagesAction chains {@see ModerateAdJob} after it, so an
 * ad waiting for review is re-checked once its hashes exist.
 *
 * Each image is processed on its own so one bad file never poisons the
 * batch: errors are logged, the fields stay null and the resources cope.
 */
class ProcessAdImagesJob implements ShouldQueue
{
    use Queueable;

    // Recomputing the hashes and re-shrinking the original yield the same
    // result, so a retry is harmless.
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
        $this->afterCommit();
    }

    public function handle(AdImageProcessor $processor): void
    {
        if ($this->mediaIds === []) {
            return;
        }

        /** @var Collection<int, Media> $mediaItems */
        $mediaItems = Media::query()->whereIn('id', $this->mediaIds)->get();

        $adIdsTouched = [];

        foreach ($mediaItems as $media) {
            try {
                if (! $processor->process($media)) {
                    Log::warning('ProcessAdImagesJob: media file missing', [
                        'media_id' => $media->getKey(),
                        'disk' => $media->disk,
                    ]);

                    continue;
                }

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
            Ad::query()->whereIn('id', array_keys($adIdsTouched))->update(['updated_at' => now()]);
        }
    }
}
