<?php

declare(strict_types=1);

namespace App\Jobs\Ads;

use App\Enums\AdStatus;
use App\Enums\PlatformSetting;
use App\Models\Ad;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hourly sweep for ad lifecycle. It only reads ids and queues the work in
 * batches of `qbazaar.sweeps.batch_size`:
 *   1. Active ads whose `expires_at` has passed → {@see ExpireAdsBatchJob}.
 *   2. Active ads inside the admin-set warning window (the
 *      `ad_expiry_warning_days` platform setting) that were not warned yet
 *      → {@see WarnExpiringAdsBatchJob}.
 *
 * Unique while queued or running, so a slow run and the next hour's run never
 * overlap. Both batch jobs re-check each ad, so an ad queued twice (by a retry
 * or by the next run) is still handled once.
 *
 * Both passes walk by primary key only: combining another ORDER BY with
 * keyset chunking skips rows once the two orders disagree.
 */
class ExpireOldAdsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(SettingsService $settings): void
    {
        $now = now();
        $warningDays = $settings->integer(PlatformSetting::AD_EXPIRY_WARNING_DAYS);

        $this->dispatchBatches(
            Ad::query()->where('status', AdStatus::ACTIVE->value)->where('expires_at', '<', $now),
            fn (array $ids) => ExpireAdsBatchJob::dispatch($ids),
        );

        $this->dispatchBatches(
            Ad::query()
                ->where('status', AdStatus::ACTIVE->value)
                ->whereBetween('expires_at', [$now, $now->copy()->addDays($warningDays)])
                ->whereNull('expiring_notified_at'),
            fn (array $ids) => WarnExpiringAdsBatchJob::dispatch($ids, $warningDays),
        );
    }

    /**
     * @param Builder<Ad> $due
     * @param callable(list<string>): mixed $dispatch
     */
    private function dispatchBatches(Builder $due, callable $dispatch): void
    {
        $due->select('id')->chunkById(
            (int) config('qbazaar.sweeps.batch_size'),
            function (Collection $ads) use ($dispatch): void {
                /** @var list<string> $ids */
                $ids = $ads->modelKeys();
                $dispatch($ids);
            },
        );
    }
}
