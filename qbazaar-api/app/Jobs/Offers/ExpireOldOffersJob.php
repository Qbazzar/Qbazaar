<?php

declare(strict_types=1);

namespace App\Jobs\Offers;

use App\Enums\OfferStatus;
use App\Events\Offers\OfferExpired;
use App\Models\Offer;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Daily sweeper for offers whose pending window has closed.
 *
 * Per-row processing (instead of a single mass UPDATE) so the OfferExpired
 * event fires with a fully hydrated model — listeners and FE subscribers
 * expect the same payload shape as the manual reject/withdraw paths.
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

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $now = now()->toDateTimeImmutable();

        $this->expirePastDueOffers($now);
    }

    private function expirePastDueOffers(DateTimeInterface $now): void
    {
        $offers = Offer::query()
            ->where('status', OfferStatus::PENDING->value)
            ->where('expires_at', '<=', $now)
            ->lazyById(100);

        foreach ($offers as $offer) {
            $offer->forceFill([
                'status' => OfferStatus::EXPIRED->value,
                'updated_at' => $now,
            ])->save();

            // Notify the BUYER on their user channel — the seller
            // is the party who left the offer sitting, so the
            // buyer benefits most from the realtime nudge.
            OfferExpired::dispatch($offer->fresh() ?? $offer, $offer->buyer_id);
        }
    }
}
