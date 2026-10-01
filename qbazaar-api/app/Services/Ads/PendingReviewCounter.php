<?php

declare(strict_types=1);

namespace App\Services\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Number of ads waiting for review, shown on every admin page. Cached under
 * one shared key (the count is the same for every reviewer) and forgotten by
 * AdReviewQueueObserver whenever an ad's status changes; the TTL only bounds
 * drift from writes that bypass Eloquent.
 */
class PendingReviewCounter
{
    public const CACHE_KEY = 'admin:ads:pending-review-count';

    public function __construct(
        private readonly Cache $cache,
    ) {}

    public function count(): int
    {
        return (int) $this->cache->remember(
            self::CACHE_KEY,
            (int) config('qbazaar.admin.pending_review_count_cache_seconds'),
            static fn (): int => Ad::query()->where('status', AdStatus::PENDING->value)->count(),
        );
    }

    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }
}
