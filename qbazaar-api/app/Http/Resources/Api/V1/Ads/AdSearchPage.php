<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Ads;

use App\Models\Ad;
use App\Models\User;
use App\Services\Ads\ViewerFavorites;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * The `{success, data, meta}` envelope of a filtered ad list, shared by
 * `GET /search` and `GET /ads`: the page of cards (with the viewer's
 * favourite flags) and `meta` with the paging numbers and `meta.facets`.
 *
 * It is pre-shaped on purpose: the response wrapper drops any `meta` key it
 * does not know, so `facets` would not survive a plain resource collection.
 *
 * @phpstan-import-type SearchResult from \App\Services\Search\AdSearchService
 */
class AdSearchPage
{
    public function __construct(private readonly ViewerFavorites $favorites) {}

    /**
     * @param SearchResult $result
     * @return array{success: true, data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function render(Request $request, array $result): array
    {
        /** @var LengthAwarePaginator<int, Ad> $paginator */
        $paginator = $result['paginator'];
        $viewer = $request->user('sanctum');

        $this->favorites->mark($viewer instanceof User ? $viewer : null, $paginator->items());

        return [
            'success' => true,
            'data' => array_values(array_map(
                fn (Ad $ad): array => (new AdSummaryResource($ad))->toArray($request),
                $paginator->items(),
            )),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->currentPage() < $paginator->lastPage(),
                'facets' => $result['meta_facets'],
            ],
        ];
    }
}
