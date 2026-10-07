<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Ad;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
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

    private const FACETS = ['category_slug', 'location_slug', 'condition', 'price_type'];

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
            $raw = $this->rawSearch($query, [
                'filter' => $filter,
                'sort' => $sort,
                'hitsPerPage' => $perPage,
                'page' => $page,
                'facets' => self::FACETS,
                'attributesToRetrieve' => ['id'],
                'attributesToHighlight' => [],
            ]);

            return [
                'paginator' => new LengthAwarePaginator(
                    items: $this->hydrate($raw),
                    total: is_int($raw['totalHits'] ?? null) ? $raw['totalHits'] : 0,
                    perPage: $perPage,
                    currentPage: $page,
                ),
                'facets' => $this->extractFacets($raw),
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
