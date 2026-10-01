<?php

declare(strict_types=1);

namespace App\Jobs\Ads;

use App\Enums\AdStatus;
use App\Enums\PlatformSetting;
use App\Enums\QueueName;
use App\Events\Ads\AdExpiringSoon;
use App\Exceptions\DomainException;
use App\Models\Ad;
use App\Services\Ads\AdLifecycleService;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Daily sweeper for ad lifecycle.
 *
 * Two passes:
 *   1. Active ads whose `expires_at` is in the past → EXPIRED through
 *      AdLifecycleService, which fires AdExpired.
 *   2. Active ads expiring within the admin-set warning window (the
 *      `ad_expiry_warning_days` platform setting) → fire AdExpiringSoon once
 *      per expiry, guarded by `expiring_notified_at`.
 *
 * Pass 1 iterates row-by-row (instead of a single mass UPDATE) so that
 * Spatie\Activitylog observers, Scout's unsearchable() and the AdExpired
 * listener chain all run with full model context.
 *
 * Both passes walk by primary key only: combining another ORDER BY with
 * keyset chunking skips rows once the two orders disagree.
 */
class ExpireOldAdsJob implements ShouldQueue
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

    public function handle(SettingsService $settings, AdLifecycleService $lifecycle): void
    {
        $now = now();

        $this->expirePastDueAds($now, $lifecycle);
        $this->notifyExpiringSoon($now, $settings->integer(PlatformSetting::AD_EXPIRY_WARNING_DAYS));
    }

    private function expirePastDueAds(Carbon $now, AdLifecycleService $lifecycle): void
    {
        $ads = Ad::query()
            ->where('status', AdStatus::ACTIVE->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->with('user')
            ->lazyById(100);

        foreach ($ads as $ad) {
            try {
                $lifecycle->expireIfPastDue($ad);
            } catch (DomainException) {
                // Sold, edited or suspended since it was read: nothing to expire.
            }
        }
    }

    private function notifyExpiringSoon(Carbon $now, int $warningDays): void
    {
        $ads = Ad::query()
            ->where('status', AdStatus::ACTIVE->value)
            ->whereBetween('expires_at', [$now, $now->copy()->addDays($warningDays)])
            ->whereNull('expiring_notified_at')
            ->with('user')
            ->lazyById(100);

        foreach ($ads as $ad) {
            if ($this->claimExpiryWarning($ad, $now)) {
                AdExpiringSoon::dispatch($ad);
            }
        }
    }

    /**
     * Conditional update so an overlapping run cannot warn the same ad twice;
     * a query-level write also keeps Scout and the activity log out of it.
     */
    private function claimExpiryWarning(Ad $ad, Carbon $now): bool
    {
        return Ad::query()
            ->whereKey($ad->getKey())
            ->whereNull('expiring_notified_at')
            ->update(['expiring_notified_at' => $now]) === 1;
    }
}
