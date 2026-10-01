<?php

declare(strict_types=1);

namespace App\Jobs\Offers;

use App\Enums\OfferStatus;
use App\Enums\QueueName;
use App\Models\Offer;
use App\Services\Offers\OfferTransitionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Daily sweeper for offers whose pending window has closed.
 *
 * Per-row processing (instead of a single mass UPDATE) so each offer goes
 * through the locked transition and fires OfferExpired with a hydrated model.
 *
 * Cohort is small (one day's worth of stale offers) so readability beats
 * raw throughput here. Walking by primary key alone (lazyById) keeps a
 * backlog from being skipped, which an extra ORDER BY would cause.
 *
 * Scheduled daily at 02:30 Asia/Qatar in bootstrap/app.php — runs after
 * ExpireOldAdsJob (02:00) so the ad-status invariants the job depends on
 * are already settled when the offer sweep starts.
 */
class ExpireOldOffersJob implements ShouldQueue
{
    use Queueable;

    // Every row is re-checked under its lock, so a retry after a timeout
    // resumes where the killed run stopped.
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [300, 900];

    public int $timeout = 1500;

    public function __construct()
    {
        $this->onQueue(QueueName::LOW);
    }

    public function handle(OfferTransitionService $transitions): void
    {
        $dueOffers = Offer::query()
            ->where('status', OfferStatus::PENDING->value)
            ->where('expires_at', '<=', now())
            ->lazyById(100);

        foreach ($dueOffers as $offer) {
            $transitions->expireIfDue($offer);
        }
    }
}
