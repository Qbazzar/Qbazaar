<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Data\Catalog\AdFeedFilters;
use App\Enums\AdSort;
use App\Models\Ad;
use App\Services\Catalog\CategoryHierarchy;
use App\Services\Catalog\LocationHierarchy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

/**
 * The public ad feed (`GET /ads`) and the "fetch these ads" lookup
 * (`GET /ads?ids=`), both limited to publicly listed ads.
 */
class ListPublicAdsAction
{
    private const PER_PAGE = 20;

    private const RELATIONS = ['category', 'location', 'primaryImage'];

    public function __construct(
        private readonly CategoryHierarchy $categories,
        private readonly LocationHierarchy $locations,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Ad>
     */
    public function feed(AdFeedFilters $filters): LengthAwarePaginator
    {
        $query = Ad::query()
            ->publiclyListed()
            ->with(self::RELATIONS)
            ->when($filters->categoryId !== null, fn (Builder $q) => $q->whereIn('category_id', $this->categories->descendantsOf((string) $filters->categoryId)))
            ->when($filters->locationId !== null, fn (Builder $q) => $q->whereIn('location_id', $this->locations->descendantsOf((string) $filters->locationId)))
            // Ads without a numeric price (free/contact) drop out of a bounded range, as expected when filtering by budget.
            ->when($filters->priceMin !== null, fn (Builder $q) => $q->where('price', '>=', $filters->priceMin))
            ->when($filters->priceMax !== null, fn (Builder $q) => $q->where('price', '<=', $filters->priceMax));

        $this->applySort($query, $filters->sort);

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * Ads in the order the caller listed them; ids that are unknown or no
     * longer public are skipped rather than failing the whole lookup.
     *
     * @param list<string> $ids
     * @return LengthAwarePaginator<int, Ad>
     */
    public function byIds(array $ids): LengthAwarePaginator
    {
        $position = array_flip($ids);

        $ads = Ad::query()
            ->publiclyListed()
            ->whereKey($ids)
            ->with(self::RELATIONS)
            ->get()
            ->sortBy(fn (Ad $ad): int => $position[$ad->id])
            ->values();

        return new Paginator(
            items: $ads,
            total: $ads->count(),
            perPage: max(1, count($ids)),
            currentPage: 1,
            options: ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * @param Builder<Ad> $query
     */
    private function applySort(Builder $query, AdSort $sort): void
    {
        // The id tie-breaker keeps pages stable when many ads share a price or
        // view count, and matches the primary key InnoDB appends to every
        // (status, column) index, so the sort is still read off the index.
        match ($sort) {
            AdSort::PRICE_ASC => $query->orderBy('price')->orderBy('id'),
            AdSort::PRICE_DESC => $query->orderByDesc('price')->orderByDesc('id'),
            AdSort::OLDEST => $query->orderBy('published_at')->orderBy('id'),
            AdSort::MOST_VIEWED => $query->orderByDesc('views_count')->orderedForFeed()->orderByDesc('id'),
            AdSort::LATEST, AdSort::DISTANCE => $query->orderedForFeed()->orderByDesc('id'),
        };
    }
}
