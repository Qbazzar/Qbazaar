<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Cache;

/**
 * Single place that knows which cached catalog views depend on what, so
 * callers only report *what* changed.
 */
class CatalogCache
{
    public const HOME_FEED_KEY = 'home.feed';

    public const MAIN_CATEGORIES_KEY = 'categories.main';

    public function __construct(
        private readonly CategoryHierarchy $categories,
        private readonly LocationHierarchy $locations,
        private readonly CategoryTree $tree,
        private readonly CategoryAdCounts $counts,
    ) {}

    public function taxonomyChanged(): void
    {
        $this->categories->flush();
        $this->locations->flush();
        $this->tree->flush();
        Cache::forget(self::MAIN_CATEGORIES_KEY);

        $this->listingsChanged();
    }

    public function listingsChanged(): void
    {
        $this->counts->flush();
        Cache::forget(self::HOME_FEED_KEY);
    }
}
