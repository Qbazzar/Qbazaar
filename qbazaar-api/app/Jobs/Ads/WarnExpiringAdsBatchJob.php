<?php

declare(strict_types=1);

namespace App\Jobs\Ads;

use App\Enums\AdStatus;
use App\Events\Ads\AdExpiringSoon;
use App\Models\Ad;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fires AdExpiringSoon for one batch of ads queued by {@see ExpireOldAdsJob}.
 *
 * Each ad is claimed with a conditional update of `expiring_notified_at`
 * before the event goes out, so a retry, a duplicate batch or an overlapping
 * run never warns the same ad twice. The query-level write keeps Scout and
 * the activity log out of it.
 */
class WarnExpiringAdsBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 60;

    /**
     * @param list<string> $adIds
     */
    public function __construct(
        public readonly array $adIds,
        public readonly int $warningDays,
    ) {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $now = now();

        $ads = Ad::query()
            ->whereKey($this->adIds)
            ->where('status', AdStatus::ACTIVE->value)
            ->whereBetween('expires_at', [$now, $now->copy()->addDays($this->warningDays)])
            ->whereNull('expiring_notified_at')
            ->with('user')
            ->get();

        foreach ($ads as $ad) {
            if ($this->claim($ad)) {
                AdExpiringSoon::dispatch($ad);
            }
        }
    }

    private function claim(Ad $ad): bool
    {
        return Ad::query()
            ->whereKey($ad->getKey())
            ->whereNull('expiring_notified_at')
            ->update(['expiring_notified_at' => now()]) === 1;
    }
}
