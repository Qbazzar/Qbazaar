<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Actions\Ads\ListPublicAdsAction;
use App\Data\Catalog\AdFeedFilters;
use App\Models\Ad;
use App\Services\Catalog\CategorySchema;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Meilisearch\Exceptions\CommunicationException;

/**
 * The one place that answers "which ads match these filters, and how many
 * match each filter value", for both `GET /search` and `GET /ads`.
 *
 * Controllers stay declarative — they hand validated input to this service
 * and forward the result. Two engines sit behind it:
 *  - Meilisearch, which returns the page of ids, the total and the facet
 *    distribution in one request;
 *  - the database, through the public feed query ({@see ListPublicAdsAction})
 *    and {@see AdFacetCounter}, when the Scout driver is not Meilisearch.
 *
 * `/ads` is a plain browse: it stays on the database, which has warmed
 * counters and indexes for it, and only goes to the engine for what the
 * database does badly (keywords, custom-field filters).
 *
 * Every result carries `facets` (the older `/search` block) and `meta_facets`
 * (the `meta.facets` block of both endpoints).
 *
 * @phpstan-type SearchResult array{paginator: LengthAwarePaginator<int, Ad>, facets: array<string, mixed>, meta_facets: array<string, mixed>}
 */
class AdSearchService
{
    /** @var int */
    public const DEFAULT_PER_PAGE = 20;

    /** @var int */
    public const MAX_PER_PAGE = 50;

    /** Attributes counted for every request; `/search` also counts places and price types. */
    private const array TAXONOMY_FACETS = ['category_slug', 'location_slug'];

    public function __construct(
        private readonly AdSearchCriteria $criteria,
        private readonly ListPublicAdsAction $feed,
        private readonly AdFacetCounter $counter,
        private readonly AdFacetShaper $shaper,
        private readonly CategorySchema $schema,
    ) {}

    /**
     * Run a paginated search + facet aggregation (`GET /search`).
     *
     * @param array<string, mixed> $params Validated SearchRequest payload.
     * @return SearchResult
     */
    public function search(array $params): array
    {
        $perPage = $this->resolvePerPage($params['per_page'] ?? null);

        return $this->run($params, $perPage, $this->usesMeilisearch(), true);
    }

    /**
     * The public feed with the same filters and facets (`GET /ads`).
     *
     * @param array<string, mixed> $params Validated ListAdsRequest payload.
     * @return SearchResult
     */
    public function browse(array $params): array
    {
        $needsEngine = trim((string) ($params['q'] ?? '')) !== '' || ! empty($params['custom_fields']);

        return $this->run($params, self::DEFAULT_PER_PAGE, $needsEngine && $this->usesMeilisearch(), false);
    }

    /**
     * @param array<string, mixed> $params
     * @return SearchResult
     */
    private function run(array $params, int $perPage, bool $viaEngine, bool $taxonomy): array
    {
        $page = is_numeric($params['page'] ?? null) ? max(1, (int) $params['page']) : 1;
        $categoryId = $params['category_id'] ?? null;
        $customKeys = is_string($categoryId) && $categoryId !== '' ? $this->schema->selectFilterKeys($categoryId) : [];

        if (! $viaEngine) {
            return $this->viaDatabase($params, $perPage, $page, $customKeys, $taxonomy);
        }

        return $this->viaEngine($params, $perPage, $page, $customKeys, $taxonomy);
    }

    /**
     * @param array<string, mixed> $params
     * @param list<string> $customKeys
     * @return SearchResult
     */
    private function viaDatabase(array $params, int $perPage, int $page, array $customKeys, bool $taxonomy): array
    {
        $filters = AdFeedFilters::fromParams($params);
        $distribution = $this->counter->count($filters, $customKeys, $taxonomy);

        return [
            'paginator' => $this->feed->feed($filters, $perPage, $page),
            'facets' => $this->shaper->legacy($distribution),
            'meta_facets' => $this->shaper->meta($distribution, $customKeys),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @param list<string> $customKeys
     * @return SearchResult
     */
    private function viaEngine(array $params, int $perPage, int $page, array $customKeys, bool $taxonomy): array
    {
        $query = isset($params['q']) && is_string($params['q']) ? trim($params['q']) : '';

        try {
            $raw = $this->rawSearch($query, [
                'filter' => $this->criteria->filter($params),
                'sort' => $this->criteria->sort($params),
                'hitsPerPage' => $perPage,
                'page' => $page,
                'facets' => $this->facetAttributes($customKeys, $taxonomy),
                'attributesToRetrieve' => ['id'],
                'attributesToHighlight' => [],
            ]);

            return $this->engineResult($raw, $perPage, $page, $customKeys);
        } catch (CommunicationException|GuzzleConnectException $e) {
            // Meilisearch unreachable — surface an empty result set instead
            // of a 500 so the UI degrades to "no matches" rather than a
            // hard error. Logged at warning so ops still gets paged in prod.
            // The database is deliberately not used instead: a keyword scan
            // there during an outage would take the primary down with it.
            Log::warning('Search engine unavailable, returning empty result set', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);

            return $this->engineResult([], $perPage, $page, $customKeys);
        }
    }

    /**
     * @param array<string, mixed> $raw
     * @param list<string> $customKeys
     * @return SearchResult
     */
    private function engineResult(array $raw, int $perPage, int $page, array $customKeys): array
    {
        /** @var array<string, array<string, int>> $distribution */
        $distribution = is_array($raw['facetDistribution'] ?? null) ? $raw['facetDistribution'] : [];

        return [
            'paginator' => new LengthAwarePaginator(
                items: $this->hydrate($raw),
                total: is_int($raw['totalHits'] ?? null) ? $raw['totalHits'] : 0,
                perPage: $perPage,
                currentPage: $page,
            ),
            'facets' => $this->shaper->legacy($distribution),
            'meta_facets' => $this->shaper->meta($distribution, $customKeys),
        ];
    }

    /**
     * @param list<string> $customKeys
     * @return list<string>
     */
    private function facetAttributes(array $customKeys, bool $taxonomy): array
    {
        $attributes = $taxonomy ? [...self::TAXONOMY_FACETS, 'condition', 'price_type'] : ['condition'];

        return [
            ...$attributes,
            'ad_type', 'shipping', 'seller_type', 'buy_now',
            ...array_map(static fn (string $key): string => 'custom_fields.' . $key, $customKeys),
        ];
    }

    /**
     * Cache-friendly title prefix suggestions. Backed by the same index as
     * full search — Meilisearch's prefix matching is already typo-tolerant
     * so we don't need a dedicated suggestions index.
     *
     * @return list<string>
     */
    public function suggest(string $query): array
    {
        $query = trim($query);
        if ($query === '' || ! $this->usesMeilisearch()) {
            return [];
        }

        try {
            $raw = $this->rawSearch($query, [
                'limit' => 10,
                'attributesToRetrieve' => ['id', 'title'],
                'attributesToHighlight' => [],
            ]);
        } catch (CommunicationException|GuzzleConnectException $e) {
            Log::warning('Search engine unavailable for suggestions', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);

            return [];
        }

        /** @var list<array<string, mixed>> $hits */
        $hits = is_array($raw['hits'] ?? null) ? $raw['hits'] : [];

        $titles = [];
        foreach ($hits as $hit) {
            if (isset($hit['title']) && is_string($hit['title'])) {
                $titles[] = $hit['title'];
            }
        }

        // De-duplicate case-insensitively while preserving relevance order.
        $seen = [];
        $unique = [];
        foreach ($titles as $title) {
            $key = mb_strtolower($title);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $title;
            }
            if (count($unique) >= 10) {
                break;
            }
        }

        return $unique;
    }

    private function resolvePerPage(mixed $candidate): int
    {
        $value = is_numeric($candidate) ? (int) $candidate : self::DEFAULT_PER_PAGE;

        return max(1, min(self::MAX_PER_PAGE, $value));
    }

    /**
     * The search callbacks speak the Meilisearch SDK; any other Scout engine
     * hands them an Eloquent builder instead and they would throw a TypeError.
     * Those installs answer from the database instead.
     */
    private function usesMeilisearch(): bool
    {
        return config('scout.driver') === 'meilisearch';
    }

    /**
     * One Meilisearch request returns the page of ids, the total and the
     * facet distribution together.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function rawSearch(string $query, array $options): array
    {
        $raw = Ad::search($query, fn ($meilisearch, string $q, array $defaults): mixed => $meilisearch->rawSearch($q, [...$defaults, ...$options]))->raw();

        return is_array($raw) ? $raw : [];
    }

    /**
     * Loads the hit ids in one query with what AdSummaryResource renders,
     * keeping Meilisearch's ranking. Ads deleted or no longer listed since
     * they were indexed are dropped: the index catches up through the queue.
     *
     * @param array<string, mixed> $raw
     * @return list<Ad>
     */
    private function hydrate(array $raw): array
    {
        $hits = is_array($raw['hits'] ?? null) ? $raw['hits'] : [];
        $ids = array_values(array_filter(array_column($hits, 'id'), 'is_string'));

        if ($ids === []) {
            return [];
        }

        $ads = Ad::query()->whereKey($ids)->publiclyListed()->with(['category', 'location', 'primaryImage'])->get()->keyBy('id');

        return array_values(array_filter(array_map(fn (string $id): ?Ad => $ads->get($id), $ids)));
    }
}
