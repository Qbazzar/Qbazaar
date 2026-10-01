<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Ads\AdSummaryResource;
use App\Models\Ad;
use App\Services\Ads\ViewerFavorites;
use App\Services\Catalog\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/v1/ads/featured — up to 12 admin-curated featured ads.
 *
 * Featured ads carry `ads.featured = true`, surfaced on the homepage hero
 * grid. Ordered by most-recently-published first; ties are broken by id so
 * the order is stable across cache rebuilds.
 *
 * Only the id list is cached, under one shared key, and {@see CatalogCache}
 * drops it whenever an ad enters or leaves the listings or is (un)featured.
 * The rows are re-read with the public-listing filter, so an ad that stops
 * being public between those events never shows.
 *
 * @group Ads
 */
class FeaturedAdsController extends Controller
{
    private const RESULT_LIMIT = 12;

    private const CACHE_TTL_SECONDS = 300;

    /**
     * @unauthenticated
     */
    public function __invoke(Request $request, ViewerFavorites $favorites): JsonResponse
    {
        /** @var list<string> $featuredIds */
        $featuredIds = Cache::remember(
            CatalogCache::FEATURED_ADS_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->resolveFeaturedIds(),
        );

        // Return a plain JSON array (already-shaped success envelope) so the
        // ApiResponseWrapper doesn't double-wrap an `AnonymousResourceCollection`
        // (which has a `data` key but no `meta`, tripping the "plain data"
        // branch of the wrapper and yielding `{data: {data: [...]}}`).
        if ($featuredIds === []) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $ads = Ad::query()
            ->whereIn('id', $featuredIds)
            ->publiclyListed()
            ->with(['category', 'location', 'primaryImage'])
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->get();

        $favorites->mark($this->viewer($request), $ads);

        $items = $ads
            ->map(fn (Ad $ad): array => (new AdSummaryResource($ad))->toArray($request))
            ->all();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * @return list<string>
     */
    private function resolveFeaturedIds(): array
    {
        /** @var list<string> $ids */
        $ids = Ad::query()
            ->publiclyListed()
            ->where('featured', true)
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->limit(self::RESULT_LIMIT)
            ->pluck('id')
            ->all();

        return $ids;
    }
}
