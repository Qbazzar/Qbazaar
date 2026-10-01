<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Live ad counters per node of a tree (the node plus all its descendants),
 * rebuilt by the catalog warmer and read from the cache by requests.
 */
abstract class CachedListingCounts
{
    /** @var array<string, array{ads_count: int, today_count: int, own_count: int}>|null */
    private ?array $counts = null;

    public function __construct(
        private readonly ListingCounter $counter,
        private readonly WarmedCache $cache,
    ) {}

    /** @return 'category_id'|'location_id' */
    abstract protected function column(): string;

    abstract protected function hierarchy(): CachedHierarchy;

    abstract protected function cacheKeyPrefix(): string;

    /**
     * @return array{ads_count: int, today_count: int, own_count: int}
     */
    public function for(string $nodeId): array
    {
        return $this->all()[$nodeId] ?? ListingCounter::empty();
    }

    /**
     * @return array<string, array{ads_count: int, today_count: int, own_count: int}>
     */
    public function all(): array
    {
        return $this->counts ??= $this->cache->get($this->cacheKey(), $this->ttlSeconds(), $this->count(...));
    }

    public function refresh(): void
    {
        $this->counts = $this->cache->put($this->cacheKey(), $this->ttlSeconds(), $this->count(...));
    }

    public function flush(): void
    {
        $this->counts = null;
        Cache::forget($this->cacheKey());
    }

    /**
     * @return array<string, array{ads_count: int, today_count: int, own_count: int}>
     */
    private function count(): array
    {
        return $this->counter->countBy($this->column(), $this->hierarchy());
    }

    private function ttlSeconds(): int
    {
        return (int) config('qbazaar.catalog.counts_cache_seconds');
    }

    /**
     * Keyed by the local date so `today_count` resets at midnight instead of when the entry expires.
     */
    private function cacheKey(): string
    {
        return $this->cacheKeyPrefix() . Carbon::now((string) config('qbazaar.timezone_display'))->toDateString();
    }
}
