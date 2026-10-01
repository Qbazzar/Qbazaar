<?php

declare(strict_types=1);

namespace App\Jobs\Ads;

use App\Actions\Ads\ModeratePendingAdAction;
use App\Jobs\ProcessAdImagesJob;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Auto-moderates an ad waiting for review, off the publish request.
 *
 * Queued when the ad is submitted and chained after {@see ProcessAdImagesJob}
 * when new photos are hashed, because either can finish first. Unique until
 * processing starts, so a burst of dispatches collapses into one run that
 * still sees the latest hashes. Re-running is safe: it recomputes and
 * replaces the stored result.
 */
class ModerateAdJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public const QUEUE = 'media';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly string $adId,
    ) {
        $this->onQueue(self::QUEUE);
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->adId;
    }

    public function handle(ModeratePendingAdAction $moderate): void
    {
        $moderate($this->adId);
    }

    public function failed(?Throwable $exception): void
    {
        app(ModeratePendingAdAction::class)->alertWithoutHints($this->adId);
    }
}
