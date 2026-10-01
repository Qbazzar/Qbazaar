<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Ad;
use App\Services\Catalog\CatalogCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Drops the featured-ads list when a featured ad enters, leaves or moves
 * within the public listings, or is (un)featured. The home feed and the
 * category/place counters are not touched here; the catalog warmer rebuilds
 * them on a schedule.
 */
class AdListingCacheObserver implements ShouldHandleEventsAfterCommit
{
    private const LISTING_ATTRIBUTES = ['status', 'published_at', 'featured', 'reserved_at'];

    public function __construct(private readonly CatalogCache $cache) {}

    public function created(Ad $ad): void
    {
        if ($ad->featured) {
            $this->cache->featuredAdsChanged();
        }
    }

    public function updated(Ad $ad): void
    {
        $concernsFeatured = $ad->featured || $ad->wasChanged('featured');

        if ($concernsFeatured && $ad->wasChanged(self::LISTING_ATTRIBUTES)) {
            $this->cache->featuredAdsChanged();
        }
    }

    public function deleted(Ad $ad): void
    {
        if ($ad->featured) {
            $this->cache->featuredAdsChanged();
        }
    }

    public function restored(Ad $ad): void
    {
        if ($ad->featured) {
            $this->cache->featuredAdsChanged();
        }
    }
}
