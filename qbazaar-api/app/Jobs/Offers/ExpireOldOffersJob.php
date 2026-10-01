<?php

declare(strict_types=1);

namespace App\Jobs\Offers;

use App\Enums\OfferStatus;
use App\Models\Offer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hourly sweep for offers whose pending window has closed. It only reads
 * ids and queues {@see ExpireOffersBatchJob} per `qbazaar.sweeps.batch_size`
 * offers; unique while queued or running so runs never overlap.
 *
 * Walking by primary key alone (chunkById) keeps a backlog from being
 * skipped, which an extra ORDER BY would cause.
 *
 * Scheduled at half past every hour in bootstrap/app.php, 30 minutes after
 * ExpireOldAdsJob, so the ad-status invariants offers depend on have settled.
 */
class ExpireOldOffersJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        Offer::query()
            ->where('status', OfferStatus::PENDING->value)
            ->where('expires_at', '<=', now())
            ->select('id')
            ->chunkById((int) config('qbazaar.sweeps.batch_size'), function (Collection $offers): void {
                /** @var list<string> $ids */
                $ids = $offers->modelKeys();
                ExpireOffersBatchJob::dispatch($ids);
            });
    }
}
