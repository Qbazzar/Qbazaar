<?php

declare(strict_types=1);

namespace App\Jobs\Ads;

use App\Data\Moderation\ModerationResult;
use App\Enums\AdStatus;
use App\Enums\QueueName;
use App\Models\Ad;
use App\Services\Moderation\DuplicateImageDetector;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Compares a pending ad's image hashes with other sellers' live ads and
 * records the outcome on the ad's moderation result. The scan grows with the
 * catalogue, which is why it runs here instead of in the publish request.
 *
 * It is dispatched when the ad is submitted and again after its images are
 * hashed, because either can finish first. Unique until processing starts,
 * so a burst of dispatches collapses into one run that still sees the
 * latest hashes.
 */
class DetectDuplicateImagesJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $timeout = 120;

    public function __construct(
        public readonly string $adId,
    ) {
        $this->onQueue(QueueName::MEDIA);
    }

    public function uniqueId(): string
    {
        return $this->adId;
    }

    public function handle(DuplicateImageDetector $detector): void
    {
        $ad = Ad::query()->find($this->adId);

        if ($ad?->status !== AdStatus::PENDING) {
            return;
        }

        $duplicateAdIds = $detector->findDuplicateAdIds($ad);

        DB::transaction(function () use ($duplicateAdIds): void {
            /** @var Ad|null $locked */
            $locked = Ad::query()->lockForUpdate()->find($this->adId);

            if ($locked?->status !== AdStatus::PENDING) {
                return;
            }

            $result = $locked->moderation_result ?? ModerationResult::clean();

            // Quiet save: the result is reviewer metadata, not a listing or search change.
            $locked->forceFill(['moderation_result' => $result->withDuplicateImages($duplicateAdIds)])->saveQuietly();
        });
    }
}
