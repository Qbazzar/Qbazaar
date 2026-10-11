<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Services\Catalog\CatalogCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Asks for a debounced home feed rebuild when an ad enters or leaves the
 * listings, and drops the featured-ads list when a featured ad enters, leaves or moves
 * within the public listings, or is (un)featured. The category/place
 * counters are rebuilt by the same warm job.
 */
class AdListingCacheObserver implements ShouldHandleEventsAfterCommit
{
    private const LISTING_ATTRIBUTES = ['status', 'published_at', 'featured', 'reserved_at'];

    public function __construct(private readonly CatalogCache $cache) {}

    public function created(Ad $ad): void
    {
        if ($ad->status === AdStatus::ACTIVE) {
            $this->cache->listingsChanged();
        }

        if ($ad->featured) {
            $this->cache->featuredAdsChanged();
        }
    }

    public function updated(Ad $ad): void
    {
        if ($this->enteredOrLeftListings($ad)) {
            $this->cache->listingsChanged();
        }

        $concernsFeatured = $ad->featured || $ad->wasChanged('featured');

        if ($concernsFeatured && $ad->wasChanged(self::LISTING_ATTRIBUTES)) {
            $this->cache->featuredAdsChanged();
        }
    }

    public function deleted(Ad $ad): void
    {
        if ($ad->status === AdStatus::ACTIVE) {
            $this->cache->listingsChanged();
        }

        if ($ad->featured) {
            $this->cache->featuredAdsChanged();
        }
    }

    public function restored(Ad $ad): void
    {
        if ($ad->status === AdStatus::ACTIVE) {
            $this->cache->listingsChanged();
        }

        if ($ad->featured) {
            $this->cache->featuredAdsChanged();
        }
    }

    /**
     * The previous status is already overwritten when this runs after commit,
     * so any status or publish-date change that is not a draft/pending
     * shuffle counts: the rebuild is debounced and cheap to repeat.
     */
    private function enteredOrLeftListings(Ad $ad): bool
    {
        return $ad->wasChanged(['status', 'published_at'])
            && ! in_array($ad->status, [AdStatus::DRAFT, AdStatus::PENDING], true);
    }
}
