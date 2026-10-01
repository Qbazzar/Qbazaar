<?php

declare(strict_types=1);

namespace App\Jobs\Ads;

use App\Enums\AdStatus;
use App\Enums\QueueName;
use App\Exceptions\DomainException;
use App\Models\Ad;
use App\Services\Ads\AdLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Expires one batch of past-due ads queued by {@see ExpireOldAdsJob}.
 *
 * Each ad goes through AdLifecycleService in its own short locked
 * transaction, so the activity log, Scout and the AdExpired listeners run
 * with full model context. The service re-checks status and `expires_at`
 * under the lock, which makes a retry or a duplicate batch harmless.
 */
class ExpireAdsBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 60;

    /**
     * @param list<string> $adIds
     */
    public function __construct(public readonly array $adIds)
    {
        $this->onQueue(QueueName::LOW);
    }

    public function handle(AdLifecycleService $lifecycle): void
    {
        $ads = Ad::query()
            ->whereKey($this->adIds)
            ->where('status', AdStatus::ACTIVE->value)
            ->with('user')
            ->get();

        foreach ($ads as $ad) {
            try {
                $lifecycle->expireIfPastDue($ad);
            } catch (DomainException) {
                // Sold, edited or suspended since it was read: nothing to expire.
            }
        }
    }
}
