<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Cache;

/**
 * Single place that knows which cached catalog views depend on what, so
 * callers only report *what* changed.
 *
 * Ads entering or leaving the listings do not flush the home feed or the
 * counters: those are rebuilt on a schedule by WarmCatalogCacheJob, because
 * flushing them per ad event turned every busy minute into a rebuild storm.
 */
class CatalogCache
{
    public const HOME_FEED_KEY = 'home.feed';

    public const MAIN_CATEGORIES_KEY = 'categories.main';

    public const LOCATION_TREE_KEY = 'locations.qatar';

    public const FEATURED_ADS_KEY = 'ads.featured.v1';

    public function __construct(
        private readonly CategoryHierarchy $categories,
        private readonly LocationHierarchy $locations,
        private readonly CategoryTree $tree,
        private readonly CategoryAdCounts $categoryCounts,
        private readonly LocationAdCounts $locationCounts,
        private readonly CategorySchema $schema,
        private readonly CategoryPageCache $categoryPages,
    ) {}

    /**
     * Rare admin edits: the counters roll up along the tree and the home feed
     * embeds category names, so both are dropped and rebuilt on next read.
     */
    public function taxonomyChanged(): void
    {
        $this->categories->flush();
        $this->locations->flush();
        $this->tree->flush();
        $this->schema->flush();
        $this->categoryCounts->flush();
        $this->locationCounts->flush();
        $this->categoryPages->flush();
        Cache::forget(self::MAIN_CATEGORIES_KEY);
        Cache::forget(self::LOCATION_TREE_KEY);
        Cache::forget(self::HOME_FEED_KEY);

        $this->featuredAdsChanged();
    }

    /**
     * The featured list caches ids only, so it is cheap to rebuild and is
     * dropped as soon as a featured ad changes.
     */
    public function featuredAdsChanged(): void
    {
        Cache::forget(self::FEATURED_ADS_KEY);
    }
}
