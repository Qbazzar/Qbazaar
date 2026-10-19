<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Data\Catalog\AdFeedFilters;
use App\Enums\AdType;
use App\Enums\PriceType;
use App\Models\Ad;
use App\Services\Catalog\CategoryHierarchy;
use App\Services\Catalog\LocationHierarchy;
use Illuminate\Database\Eloquent\Builder;

/**
 * The database reading of the catalog filters: what {@see AdSearchCriteria}
 * says to Meilisearch, said to MySQL. The public list and the facet counts
 * both build on it, so a filter cannot mean one thing in the list and
 * another in its counts.
 */
class AdListingFilters
{
    /**
     * SQL twin of {@see Ad::acceptsBuyNow()} (the live state of the ad is
     * already covered by the publicly-listed scope).
     */
    public static function buyNowSql(): string
    {
        $priced = array_map(
            static fn (PriceType $type): string => "'" . $type->value . "'",
            array_filter(PriceType::cases(), static fn (PriceType $type): bool => ! $type->requiresNullPrice()),
        );

        return sprintf("ad_type = '%s' AND price > 0 AND price_type IN (%s) AND quantity >= 1", AdType::OFFERING->value, implode(', ', $priced));
    }

    /** Words of a keyword that are matched; more would only slow the scan. */
    private const int MAX_KEYWORD_WORDS = 6;

    public function __construct(
        private readonly CategoryHierarchy $categories,
        private readonly LocationHierarchy $locations,
    ) {}

    /**
     * @param Builder<Ad> $query
     * @return Builder<Ad>
     */
    public function apply(Builder $query, AdFeedFilters $filters): Builder
    {
        return $query
            ->when($filters->categoryId !== null, fn (Builder $q) => $q->whereIn('category_id', $this->categories->descendantsOf((string) $filters->categoryId)))
            ->when($filters->locationId !== null, fn (Builder $q) => $q->whereIn('location_id', $this->locations->descendantsOf((string) $filters->locationId)))
            // Ads without a numeric price (free/contact) drop out of a bounded range, as expected when filtering by budget.
            ->when($filters->priceMin !== null, fn (Builder $q) => $q->where('price', '>=', $filters->priceMin))
            ->when($filters->priceMax !== null, fn (Builder $q) => $q->where('price', '<=', $filters->priceMax))
            ->when($filters->condition !== null, fn (Builder $q) => $q->where('condition', $filters->condition))
            ->when($filters->priceType !== null, fn (Builder $q) => $q->where('price_type', $filters->priceType))
            ->when($filters->adType !== null, fn (Builder $q) => $q->where('ad_type', $filters->adType))
            ->when($filters->shipping !== null, fn (Builder $q) => $q->where('shipping', $filters->shipping))
            ->when($filters->sellerType !== null, fn (Builder $q) => $q->where('seller_type', $filters->sellerType))
            ->when($filters->buyNow, fn (Builder $q) => $q->whereRaw(self::buyNowSql()))
            ->tap(fn (Builder $q) => $this->keyword($q, $filters->keyword))
            ->tap(fn (Builder $q) => $this->customFields($q, $filters->customFields));
    }

    /**
     * Every word must appear in the title or description.
     *
     * @param Builder<Ad> $query
     */
    private function keyword(Builder $query, ?string $keyword): void
    {
        $words = preg_split('/\s+/u', (string) $keyword, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach (array_slice($words, 0, self::MAX_KEYWORD_WORDS) as $word) {
            $like = '%' . addcslashes($word, '%_\\') . '%';

            $query->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like));
        }
    }

    /**
     * Same shape as the search engine: `{make: "Toyota"}` for equality and
     * `{year: {min, max}}` for a range. Keys other than plain identifiers are dropped.
     *
     * @param Builder<Ad> $query
     * @param array<string, mixed> $fields
     */
    private function customFields(Builder $query, array $fields): void
    {
        foreach ($fields as $key => $value) {
            if (! is_string($key) || preg_match('/^[a-z0-9_]+$/i', $key) !== 1) {
                continue;
            }

            $column = 'custom_fields->' . $key;

            match (true) {
                is_array($value) => $this->range($query, $column, $value),
                is_string($value) && $value !== '' => $this->equals($query, $column, $value),
                default => null,
            };
        }
    }

    /**
     * A multiselect field stores a list, which matches when it holds the value.
     *
     * @param Builder<Ad> $query
     */
    private function equals(Builder $query, string $column, string $value): void
    {
        $query->where(fn (Builder $q) => $q->where($column, $value)->orWhereJsonContains($column, $value));
    }

    /**
     * @param Builder<Ad> $query
     * @param array<mixed> $bounds
     */
    private function range(Builder $query, string $column, array $bounds): void
    {
        // Compared as numbers: a JSON value is read back as text on MySQL and
        // text sorts "900" after "2015".
        $number = 'CAST(' . $query->getQuery()->getGrammar()->wrap($column) . ' AS DECIMAL(20, 4))';

        if (is_numeric($bounds['min'] ?? null)) {
            $query->whereRaw($number . ' >= ?', [(float) $bounds['min']]);
        }

        if (is_numeric($bounds['max'] ?? null)) {
            $query->whereRaw($number . ' <= ?', [(float) $bounds['max']]);
        }
    }
}
