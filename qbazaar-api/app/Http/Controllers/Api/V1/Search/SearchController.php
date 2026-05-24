<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Search\SearchRequest;
use App\Http\Resources\Api\V1\Ads\AdSummaryResource;
use App\Models\Ad;
use App\Services\Search\AdSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Sprint 6 — Meilisearch-powered ad discovery.
 *
 * Two endpoints are exposed publicly:
 *   - GET /search             — paginated keyword + filter search with facets
 *   - GET /search/suggestions — title prefix suggestions for the search bar
 *
 * Both return the canonical `{success, data, meta?, ...}` envelope via the
 * ApiResponseWrapper middleware. The controller itself stays HTTP-thin and
 * defers all engine interaction to {@see AdSearchService}.
 *
 * @group Search
 */
class SearchController extends Controller
{
    private const SUGGESTION_TTL = 300; // 5 minutes

    public function __construct(private readonly AdSearchService $search) {}

    /**
     * GET /api/v1/search — paginated search results + facet aggregations.
     *
     * @unauthenticated
     */
    public function index(SearchRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $params */
        $params = $request->validated();

        $result = $this->search->search($params);

        $paginator = $result['paginator'];

        // Map manually to plain arrays so the wrapper produces the desired
        // envelope (data + meta), matching the precedent set by
        // Reference\CategoryController and Ads\AdController.
        $items = $paginator->getCollection()
            ->map(fn (Ad $ad): array => (new AdSummaryResource($ad))->toArray($request))
            ->all();

        // Pre-shaped envelope — ApiResponseWrapper sees the `success` key and
        // leaves the payload untouched, so the sibling `facets` block survives.
        // (The default wrapper drops keys other than data + meta.)
        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->currentPage() < $paginator->lastPage(),
            ],
            'facets' => $result['facets'],
        ]);
    }

    /**
     * GET /api/v1/search/suggestions — prefix-match titles.
     *
     * Cached by lower-cased query for 5 minutes so the search bar's
     * keystroke-by-keystroke calls don't hammer Meilisearch on every
     * character.
     *
     * @unauthenticated
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $query = trim($query);

        if ($query === '') {
            return response()->json([]);
        }

        // Cap at 100 chars defensively — a 1000-char "suggestions" probe is
        // never legitimate and would just hit Meili pointlessly.
        $query = mb_substr($query, 0, 100);

        $cacheKey = 'search.suggestions:' . sha1(mb_strtolower($query));

        /** @var list<string> $suggestions */
        $suggestions = Cache::remember(
            $cacheKey,
            self::SUGGESTION_TTL,
            fn (): array => $this->search->suggest($query),
        );

        return response()->json($suggestions);
    }
}
