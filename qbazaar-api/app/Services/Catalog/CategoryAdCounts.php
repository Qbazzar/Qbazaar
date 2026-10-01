<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Container\Attributes\Scoped;

/**
 * Live ad counters per category (the category plus all its descendants).
 */
#[Scoped]
class CategoryAdCounts extends CachedListingCounts
{
    public function __construct(
        private readonly CategoryHierarchy $categories,
        ListingCounter $counter,
        WarmedCache $cache,
    ) {
        parent::__construct($counter, $cache);
    }

    protected function column(): string
    {
        return 'category_id';
    }

    protected function hierarchy(): CachedHierarchy
    {
        return $this->categories;
    }

    protected function cacheKeyPrefix(): string
    {
        return 'categories.ad_counts.';
    }
}
