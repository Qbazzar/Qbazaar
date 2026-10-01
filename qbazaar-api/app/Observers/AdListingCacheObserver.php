<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Services\Catalog\CatalogCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Flushes the category counters, the home feed and the featured list whenever
 * an ad enters, leaves or moves within the public listings (publish, expiry,
 * moderation, featuring…).
 */
class AdListingCacheObserver implements ShouldHandleEventsAfterCommit
{
    private const LISTING_ATTRIBUTES = ['status', 'category_id', 'location_id', 'published_at', 'featured'];

    public function __construct(private readonly CatalogCache $cache) {}

    public function created(Ad $ad): void
    {
        if ($ad->status === AdStatus::ACTIVE) {
            $this->cache->listingsChanged();
        }
    }

    public function updated(Ad $ad): void
    {
        if ($ad->wasChanged(self::LISTING_ATTRIBUTES)) {
            $this->cache->listingsChanged();
        }
    }

    public function deleted(Ad $ad): void
    {
        $this->cache->listingsChanged();
    }

    public function restored(Ad $ad): void
    {
        $this->cache->listingsChanged();
    }
}
