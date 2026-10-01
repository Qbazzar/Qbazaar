<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\Catalog\CatalogCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

/**
 * Flushes the cached catalog views when a category or location changes.
 */
class TaxonomyCacheObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly CatalogCache $cache) {}

    public function saved(Model $model): void
    {
        $this->cache->taxonomyChanged();
    }

    public function deleted(Model $model): void
    {
        $this->cache->taxonomyChanged();
    }
}
