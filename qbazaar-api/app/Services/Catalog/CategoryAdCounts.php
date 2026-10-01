<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Live ad counters per category (the category plus all its descendants),
 * cached briefly and flushed whenever an ad enters or leaves the public listings.
 */
#[Scoped]
class CategoryAdCounts
{
    /** @var array<string, array{ads_count: int, today_count: int, own_count: int}>|null */
    private ?array $counts = null;

    public function __construct(
        private readonly CategoryHierarchy $hierarchy,
        private readonly ListingCounter $counter,
    ) {}

    /**
     * @return array{ads_count: int, today_count: int, own_count: int}
     */
    public function for(string $categoryId): array
    {
        return $this->all()[$categoryId] ?? ListingCounter::empty();
    }

    public function flush(): void
    {
        $this->counts = null;
        Cache::forget($this->cacheKey());
    }

    /**
     * @return array<string, array{ads_count: int, today_count: int, own_count: int}>
     */
    private function all(): array
    {
        if ($this->counts === null) {
            /** @var array<string, array{ads_count: int, today_count: int, own_count: int}> $counts */
            $counts = Cache::remember(
                $this->cacheKey(),
                (int) config('qbazaar.catalog.counts_cache_seconds'),
                fn (): array => $this->counter->countBy('category_id', $this->hierarchy),
            );

            $this->counts = $counts;
        }

        return $this->counts;
    }

    /**
     * Keyed by the local date so `today_count` resets at midnight instead of when the entry expires.
     */
    private function cacheKey(): string
    {
        return 'categories.ad_counts.' . Carbon::now((string) config('qbazaar.timezone_display'))->toDateString();
    }
}
