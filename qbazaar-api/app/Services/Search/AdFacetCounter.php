<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Data\Catalog\AdFeedFilters;
use App\Enums\AccountType;
use App\Enums\AdShipping;
use App\Enums\AdType;
use App\Enums\Condition;
use App\Enums\PriceType;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Facet counts straight from the database, for installs and tests where the
 * search engine is not Meilisearch. Same keys and meaning as the engine's
 * `facetDistribution`: the counts are those of the current result set.
 *
 * One aggregate query counts every enum facet at once; categories and places
 * (only asked for by `/search`) take one grouped query each, and every
 * custom-field facet one more on its JSON path. Results are shared per filter
 * set for a short while, like the page totals of the feed.
 */
class AdFacetCounter
{
    /** @var array<string, class-string<BackedEnum>> */
    private const array ENUM_FACETS = [
        'condition' => Condition::class,
        'price_type' => PriceType::class,
        'ad_type' => AdType::class,
        'shipping' => AdShipping::class,
        'seller_type' => AccountType::class,
    ];

    public function __construct(private readonly AdListingFilters $listingFilters) {}

    /**
     * @param list<string> $customKeys
     * @return array<string, array<string, int>>
     */
    public function count(AdFeedFilters $filters, array $customKeys, bool $taxonomy): array
    {
        $fresh = (int) config('qbazaar.search.facets_cache_seconds');
        $compute = fn (): array => $this->compute($filters, $customKeys, $taxonomy);

        if ($fresh <= 0) {
            return $compute();
        }

        $key = 'search.facets.v1.' . sha1($filters->fingerprint() . '|' . implode(',', $customKeys) . '|' . (int) $taxonomy);

        /** @var array<string, array<string, int>> */
        return Cache::flexible($key, [$fresh, $fresh * 5], $compute);
    }

    /**
     * @param list<string> $customKeys
     * @return array<string, array<string, int>>
     */
    private function compute(AdFeedFilters $filters, array $customKeys, bool $taxonomy): array
    {
        $distribution = $this->enumFacets($filters);

        if ($taxonomy) {
            $distribution['category_slug'] = $this->slugCounts($filters, 'category_id', Category::class);
            $distribution['location_slug'] = $this->slugCounts($filters, 'location_id', Location::class);
        }

        foreach ($customKeys as $key) {
            $distribution['custom_fields.' . $key] = $this->customFieldCounts($filters, $key);
        }

        return $distribution;
    }

    /**
     * @return Builder<Ad>
     */
    private function base(AdFeedFilters $filters): Builder
    {
        return $this->listingFilters->apply(Ad::query()->publiclyListed(), $filters);
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function enumFacets(AdFeedFilters $filters): array
    {
        $columns = [
            'COUNT(*) AS total_ads',
            'COALESCE(SUM(CASE WHEN ' . AdListingFilters::BUY_NOW_SQL . ' THEN 1 ELSE 0 END), 0) AS buy_now__true',
        ];
        $bindings = [];

        foreach (self::ENUM_FACETS as $column => $enum) {
            foreach ($enum::cases() as $case) {
                $columns[] = sprintf('COALESCE(SUM(CASE WHEN %s = ? THEN 1 ELSE 0 END), 0) AS %s__%s', $column, $column, $case->value);
                $bindings[] = $case->value;
            }
        }

        $row = $this->base($filters)->toBase()->selectRaw(implode(', ', $columns), $bindings)->first();
        $counts = (array) $row;
        $total = (int) ($counts['total_ads'] ?? 0);
        $buyNow = (int) ($counts['buy_now__true'] ?? 0);

        $distribution = ['buy_now' => $this->positive(['true' => $buyNow, 'false' => $total - $buyNow])];

        foreach (self::ENUM_FACETS as $column => $enum) {
            $values = [];
            foreach ($enum::cases() as $case) {
                $values[(string) $case->value] = (int) ($counts[$column . '__' . $case->value] ?? 0);
            }
            $distribution[$column] = $this->positive($values);
        }

        return $distribution;
    }

    /**
     * @param class-string<Category|Location> $model
     * @return array<string, int>
     */
    private function slugCounts(AdFeedFilters $filters, string $column, string $model): array
    {
        $perId = $this->base($filters)->toBase()->selectRaw($column . ' AS id, COUNT(*) AS aggregate')->groupBy($column)->pluck('aggregate', 'id');

        if ($perId->isEmpty()) {
            return [];
        }

        $slugs = $model::query()->whereKey($perId->keys()->all())->pluck('slug', 'id');

        return $this->positive($perId->mapWithKeys(fn (mixed $count, mixed $id): array => [(string) $slugs->get($id, $id) => (int) $count])->all());
    }

    /**
     * @return array<string, int>
     */
    private function customFieldCounts(AdFeedFilters $filters, string $key): array
    {
        $query = $this->base($filters)->toBase();
        $path = $query->getGrammar()->wrap('custom_fields->' . $key);

        $rows = $query->whereRaw($path . ' IS NOT NULL')->selectRaw($path . ' AS facet_value, COUNT(*) AS aggregate')->groupBy('facet_value')->pluck('aggregate', 'facet_value');

        return $this->positive($rows->mapWithKeys(fn (mixed $count, mixed $value): array => [(string) $value => (int) $count])->all());
    }

    /**
     * Values nobody matches are left out, as the engine does.
     *
     * @param array<string, int> $counts
     * @return array<string, int>
     */
    private function positive(array $counts): array
    {
        $kept = array_filter($counts, static fn (int $count): bool => $count > 0);
        arsort($kept);

        return $kept;
    }
}
