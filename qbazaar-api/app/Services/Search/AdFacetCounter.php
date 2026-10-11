<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Data\Catalog\AdFeedFilters;
use App\Enums\AccountType;
use App\Enums\AdShipping;
use App\Enums\AdType;
use App\Enums\Condition;
use App\Enums\PriceType;
use App\Jobs\Catalog\WarmCatalogCacheJob;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Services\Catalog\WarmedCache;
use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDO;
use Throwable;

/**
 * Facet counts straight from the database, for installs and tests where the
 * search engine is not Meilisearch, and for the plain `/ads` browse. Same
 * keys and meaning as the engine's `facetDistribution`: the counts are those
 * of the current result set.
 *
 * Counting walks every matching row, so it is kept off the request path:
 *  - the unfiltered counts come from the catalog warmer
 *    ({@see WarmCatalogCacheJob}) and are never computed by a request unless
 *    the cache is cold;
 *  - narrowed sets are computed once per filter set (single-flight, so a
 *    crowd on a cold key runs one query), shared for `facets_cache_seconds`,
 *    and stopped by a statement timeout, answering empty counts instead of
 *    holding the database.
 *
 * Price bounds are rounded outwards to {@see self::PRICE_STEP} before counting,
 * so a slider drag shares one cache entry instead of making one per value.
 */
class AdFacetCounter
{
    private const string VERSION_KEY = 'search.facets.version';

    private const int PRICE_STEP = 50;

    /** MariaDB / MySQL errors for a statement stopped by its time limit. */
    private const array TIMEOUT_ERRORS = [1969, 3024];

    /** @var array<string, class-string<BackedEnum>> */
    private const array ENUM_FACETS = [
        'condition' => Condition::class,
        'price_type' => PriceType::class,
        'ad_type' => AdType::class,
        'shipping' => AdShipping::class,
        'seller_type' => AccountType::class,
    ];

    public function __construct(
        private readonly AdListingFilters $listingFilters,
        private readonly WarmedCache $cache,
    ) {}

    /**
     * @param list<string> $customKeys
     * @return array<string, array<string, int>>
     */
    public function count(AdFeedFilters $filters, array $customKeys, bool $taxonomy): array
    {
        try {
            return $this->cachedOrComputed($filters, $customKeys, $taxonomy);
        } catch (FacetCountTimedOut) {
            // Thrown out of the cache's build step, so nothing was stored: the next request counts again.
            return [];
        }
    }

    /**
     * @param list<string> $customKeys
     * @return array<string, array<string, int>>
     *
     * @throws FacetCountTimedOut
     */
    private function cachedOrComputed(AdFeedFilters $filters, array $customKeys, bool $taxonomy): array
    {
        $fresh = (int) config('qbazaar.search.facets_cache_seconds');
        $requestLimit = (int) config('qbazaar.search.facet_timeout_seconds');

        if ($fresh <= 0) {
            return $this->compute($filters, $customKeys, $taxonomy, $requestLimit);
        }

        if ($this->isUnfiltered($filters, $customKeys)) {
            return $this->cache->get($this->unfilteredKey(), $this->warmedTtl(), fn (): array => $this->computeUnfiltered($requestLimit));
        }

        $rounded = $this->rounded($filters);
        $key = 'search.facets.v2.' . $this->version() . '.' . sha1($rounded->fingerprint() . '|' . implode(',', $customKeys) . '|' . (int) $taxonomy);

        return $this->cache->get($key, $fresh, fn (): array => $this->compute($rounded, $customKeys, $taxonomy, $requestLimit));
    }

    /**
     * Rebuilds the unfiltered counts; run by the catalog warmer.
     */
    public function refreshUnfiltered(): void
    {
        $limit = (int) config('qbazaar.search.facet_warm_timeout_seconds');

        try {
            $this->cache->put($this->unfilteredKey(), $this->warmedTtl(), fn (): array => $this->computeUnfiltered($limit));
        } catch (FacetCountTimedOut) {
            // The previous entry stays until it expires; requests never see an empty one.
        }
    }

    /**
     * Drops every cached count: they move to a new key version, so the old
     * entries are simply never read again and age out.
     */
    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (string) Str::ulid());
    }

    /**
     * @param list<string> $customKeys
     */
    private function isUnfiltered(AdFeedFilters $filters, array $customKeys): bool
    {
        return $customKeys === []
            && $filters->categoryId === null
            && $filters->locationId === null
            && $filters->priceMin === null
            && $filters->priceMax === null
            && ! $filters->hasDetailFilters();
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function computeUnfiltered(int $limitSeconds): array
    {
        return $this->compute(new AdFeedFilters, [], true, $limitSeconds);
    }

    private function unfilteredKey(): string
    {
        return 'search.facets.unfiltered.' . $this->version();
    }

    private function warmedTtl(): int
    {
        return (int) config('qbazaar.catalog.counts_cache_seconds');
    }

    private function version(): string
    {
        return (string) Cache::get(self::VERSION_KEY, '0');
    }

    private function rounded(AdFeedFilters $filters): AdFeedFilters
    {
        if ($filters->priceMin === null && $filters->priceMax === null) {
            return $filters;
        }

        return new AdFeedFilters(
            categoryId: $filters->categoryId,
            locationId: $filters->locationId,
            priceMin: $filters->priceMin !== null ? floor($filters->priceMin / self::PRICE_STEP) * self::PRICE_STEP : null,
            priceMax: $filters->priceMax !== null ? ceil($filters->priceMax / self::PRICE_STEP) * self::PRICE_STEP : null,
            sort: $filters->sort,
            keyword: $filters->keyword,
            condition: $filters->condition,
            priceType: $filters->priceType,
            adType: $filters->adType,
            shipping: $filters->shipping,
            sellerType: $filters->sellerType,
            buyNow: $filters->buyNow,
            customFields: $filters->customFields,
        );
    }

    /**
     * @param list<string> $customKeys
     * @return array<string, array<string, int>>
     *
     * @throws FacetCountTimedOut
     */
    private function compute(AdFeedFilters $filters, array $customKeys, bool $taxonomy, int $limitSeconds): array
    {
        try {
            return $this->withTimeout($limitSeconds, fn (): array => $this->build($filters, $customKeys, $taxonomy));
        } catch (QueryException $e) {
            if (! in_array($e->errorInfo[1] ?? null, self::TIMEOUT_ERRORS, true)) {
                throw $e;
            }

            Log::warning('Facet counts timed out, answering without counts', ['filters' => $filters->fingerprint(), 'limit_seconds' => $limitSeconds]);

            throw new FacetCountTimedOut('Facet count exceeded ' . $limitSeconds . 's', previous: $e);
        }
    }

    /**
     * Caps the statements of one computation with the server's time limit
     * (MariaDB `max_statement_time`, MySQL `max_execution_time`) and puts the
     * session's previous limit back, because the connection outlives the
     * request in queue workers.
     *
     * @template TValue
     *
     * @param Closure(): TValue $run
     * @return TValue
     */
    private function withTimeout(int $seconds, Closure $run): mixed
    {
        $connection = DB::connection();

        if ($seconds <= 0 || ! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $run();
        }

        $isMaria = str_contains((string) $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION), 'MariaDB');
        [$variable, $limit] = $isMaria ? ['max_statement_time', (string) $seconds] : ['max_execution_time', (string) ($seconds * 1000)];

        $previous = $connection->selectOne("SELECT @@session.{$variable} AS value");
        $restore = is_object($previous) && is_numeric($previous->value ?? null) ? (string) $previous->value : '0';

        $connection->statement("SET SESSION {$variable} = {$limit}");

        try {
            return $run();
        } finally {
            try {
                $connection->statement("SET SESSION {$variable} = {$restore}");
            } catch (Throwable $e) {
                // Never mask the exception that is already on its way out.
                Log::warning('Could not restore the statement time limit', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * @param list<string> $customKeys
     * @return array<string, array<string, int>>
     */
    private function build(AdFeedFilters $filters, array $customKeys, bool $taxonomy): array
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
            'COALESCE(SUM(CASE WHEN ' . AdListingFilters::buyNowSql() . ' THEN 1 ELSE 0 END), 0) AS buy_now__true',
        ];
        $bindings = [];
        $query = $this->base($filters)->toBase();
        // `condition` is a reserved word in MySQL/MariaDB, so raw column names are wrapped by the grammar.
        $grammar = $query->getGrammar();

        foreach (self::ENUM_FACETS as $column => $enum) {
            foreach ($enum::cases() as $case) {
                $columns[] = sprintf('COALESCE(SUM(CASE WHEN %s = ? THEN 1 ELSE 0 END), 0) AS %s__%s', $grammar->wrap($column), $column, $case->value);
                $bindings[] = $case->value;
            }
        }

        $row = $query->selectRaw(implode(', ', $columns), $bindings)->first();
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
