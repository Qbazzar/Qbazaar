<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Container\Attributes\Scoped;

/**
 * Live ad counters per location (the place plus all its districts).
 */
#[Scoped]
class LocationAdCounts extends CachedListingCounts
{
    public function __construct(
        private readonly LocationHierarchy $locations,
        ListingCounter $counter,
        WarmedCache $cache,
    ) {
        parent::__construct($counter, $cache);
    }

    protected function column(): string
    {
        return 'location_id';
    }

    protected function hierarchy(): CachedHierarchy
    {
        return $this->locations;
    }

    protected function cacheKeyPrefix(): string
    {
        return 'locations.ad_counts.';
    }
}
