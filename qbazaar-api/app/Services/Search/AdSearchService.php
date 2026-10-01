<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Ad;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Meilisearch\Exceptions\CommunicationException;
use stdClass;

/**
 * Encapsulates Meilisearch interaction for ad search.
 *
 * Controllers stay declarative — they hand validated input to this service
 * and forward the result envelope. All Meilisearch-specific bits (filter
 * string composition, facet distribution, ranking rule overrides) live
 * here so the engine can be swapped without touching HTTP code.
 */
class AdSearchService
{
    /** @var int */
    public const DEFAULT_PER_PAGE = 20;

    /** @var int */
    public const MAX_PER_PAGE = 50;

    public function __construct(private readonly AdSearchCriteria $criteria) {}

    /**
     * Run a paginated search + facet aggregation.
     *
     * @param array<string, mixed> $params Validated SearchRequest payload.
     * @return array{paginator: LengthAwarePaginator<int, Ad>, facets: array<string, mixed>}
     */
    public function search(array $params): array
    {
        $perPage = $this->resolvePerPage($params['per_page'] ?? null);
        $page = is_numeric($params['page'] ?? null) ? max(1, (int) $params['page']) : 1;

        $query = isset($params['q']) && is_string($params['q']) ? trim($params['q']) : '';

        $sort = $this->criteria->sort($params);
        $filter = $this->criteria->filter($params);

        if (! $this->usesMeilisearch()) {
            return $this->emptyResult($perPage, $page);
        }

        try {
            $builder = Ad::search($query, function ($meilisearch, string $q, array $options) use ($filter, $sort, $perPage, $page): mixed {
                $options['filter'] = $filter;
                $options['sort'] = $sort;
                $options['hitsPerPage'] = $perPage;
                $options['page'] = $page;
                $options['facets'] = ['category_slug', 'location_slug', 'condition', 'price_type'];

                return $meilisearch->rawSearch($q, $options);
            });

            /** @var LengthAwarePaginator<int, Ad> $paginator */
            $paginator = $builder->paginate($perPage, 'page', $page);

            // Eager-load AFTER pagination instead of via Scout's ->query()
            // callback. A query callback flips Scout's getTotalCount() into a
            // path that recomputes the total from the *current page's* ids —
            // and because our search callback pins hitsPerPage/page, that
            // recount caps at one page, corrupting `total` and `last_page`
            // (page 2 becomes unreachable). Loading on the paginated models
            // keeps the Meili `totalHits` intact and still avoids N+1.
            EloquentCollection::make($paginator->items())->load(['category', 'location', 'primaryImage']);

            // Facets need a second, hits-free Meili call (Scout doesn't expose
            // the raw response from paginate() in this version). Cheap because
            // `hitsPerPage=0` returns no documents.
            $raw = $this->fetchFacetDistribution($query, $filter, $sort);
            $facets = $this->extractFacets($raw);

            return [
                'paginator' => $paginator,
                'facets' => $facets,
            ];
        } catch (CommunicationException|GuzzleConnectException $e) {
            // Meilisearch unreachable — surface an empty result set instead
            // of a 500 so the UI degrades to "no matches" rather than a
            // hard error. Logged at warning so ops still gets paged in prod.
            Log::warning('Search engine unavailable, returning empty result set', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);

            return $this->emptyResult($perPage, $page);
        }
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
            $builder = Ad::search($query, function ($meilisearch, string $q, array $options): mixed {
                $options['limit'] = 10;
                $options['attributesToRetrieve'] = ['id', 'title'];
                $options['attributesToHighlight'] = [];

                return $meilisearch->rawSearch($q, $options);
            });

            /** @var array<string, mixed> $raw */
            $raw = $builder->raw();
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
     */
    private function usesMeilisearch(): bool
    {
        if (config('scout.driver') === 'meilisearch') {
            return true;
        }

        Log::warning('Ad search requires the meilisearch Scout driver, returning no results', [
            'driver' => config('scout.driver'),
        ]);

        return false;
    }

    /**
     * @return array{paginator: LengthAwarePaginator<int, Ad>, facets: array<string, mixed>}
     */
    private function emptyResult(int $perPage, int $page): array
    {
        return [
            'paginator' => new LengthAwarePaginator(
                items: [],
                total: 0,
                perPage: $perPage,
                currentPage: $page,
            ),
            'facets' => $this->extractFacets([]),
        ];
    }

    /**
     * Fetch the facet distribution for a query.
     *
     * Why a second call? `paginate()` hydrates Eloquent models, but the
     * underlying Scout engine in this version doesn't expose the raw Meili
     * payload back to the caller. A `hitsPerPage:0`+`facets` call is the
     * idiomatic Meilisearch pattern and cheap because no documents are
     * returned.
     *
     * @param list<string> $sort
     * @return array<string, mixed>
     */
    private function fetchFacetDistribution(string $query, string $filter, array $sort): array
    {
        $facetBuilder = Ad::search($query, function ($meilisearch, string $q, array $options) use ($filter, $sort): mixed {
            $options['filter'] = $filter;
            $options['sort'] = $sort;
            $options['hitsPerPage'] = 0;
            $options['facets'] = ['category_slug', 'location_slug', 'condition', 'price_type'];

            return $meilisearch->rawSearch($q, $options);
        });

        $raw = $facetBuilder->raw();

        return is_array($raw) ? $raw : [];
    }

    /**
     * Shape the raw Meilisearch `facetDistribution` block into the public
     * `facets` envelope. Also derives a small set of price buckets so the
     * UI can render a histogram filter without a second round-trip.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private function extractFacets(array $raw): array
    {
        /** @var array<string, array<string, int>> $distribution */
        $distribution = is_array($raw['facetDistribution'] ?? null) ? $raw['facetDistribution'] : [];

        return [
            'categories' => $distribution['category_slug'] ?? new stdClass,
            'locations' => $distribution['location_slug'] ?? new stdClass,
            'conditions' => $distribution['condition'] ?? new stdClass,
            'price_types' => $distribution['price_type'] ?? new stdClass,
            'price_buckets' => $this->derivePriceBuckets(),
        ];
    }

    /**
     * Static bucket scheme — keeps the UI simple. Real per-result histograms
     * land in a follow-up once we have query volume to size them on.
     *
     * @return list<array{label: string, min: float|null, max: float|null}>
     */
    private function derivePriceBuckets(): array
    {
        return [
            ['label' => '0-100', 'min' => 0.0, 'max' => 100.0],
            ['label' => '100-500', 'min' => 100.0, 'max' => 500.0],
            ['label' => '500-1000', 'min' => 500.0, 'max' => 1000.0],
            ['label' => '1000-5000', 'min' => 1000.0, 'max' => 5000.0],
            ['label' => '5000+', 'min' => 5000.0, 'max' => null],
        ];
    }
}
