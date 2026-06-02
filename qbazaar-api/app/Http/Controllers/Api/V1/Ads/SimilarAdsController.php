<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Enums\AdStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Ads\AdSummaryResource;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/v1/ads/{id}/similar — public list of related ads.
 *
 * Strategy (Wave B baseline):
 *   - Same category, ACTIVE only, excluding the current ad.
 *   - Latest-published-first, capped at 12 rows.
 *
 * Cached for 5 minutes per ad-id; the cache key includes the category id so
 * that admin re-categorisation of an ad invalidates implicitly (the new key
 * has no warm entry yet).
 *
 * The endpoint is public — no auth, no rate limiting beyond the global
 * `throttle:api` group. It returns AdSummaryResource shapes for parity with
 * the public feed.
 *
 * @group Ads
 */
class SimilarAdsController extends Controller
{
    private const RESULT_LIMIT = 12;

    private const CACHE_TTL_SECONDS = 300;

    /**
     * @unauthenticated
     *
     * @throws DomainException
     */
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $ad = Ad::query()->find($id);

        if ($ad === null) {
            throw new DomainException(ErrorCode::AD_NOT_FOUND);
        }

        /** @var list<string> $similarIds */
        $similarIds = Cache::remember(
            $this->cacheKey($ad),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->resolveSimilarIds($ad),
        );

        // Return a plain JSON array (already-shaped success envelope) so the
        // ApiResponseWrapper doesn't double-wrap a non-paginated
        // AnonymousResourceCollection — which carries a `data` key but no
        // `meta`, tripping the wrapper's "plain data" branch and yielding
        // `{data: {data: [...]}}`.
        if ($similarIds === []) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $ads = Ad::query()
            ->whereIn('id', $similarIds)
            ->with(['category', 'location', 'media'])
            ->orderByDesc('published_at')
            ->get();

        $items = $ads
            ->map(fn (Ad $similar): array => (new AdSummaryResource($similar))->toArray($request))
            ->all();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * Resolve the id list once and cache it — the surrounding query then
     * re-hydrates fresh models, so the cached response always reflects the
     * latest views/favorites counters without polluting the cache with the
     * full model snapshot.
     *
     * @return list<string>
     */
    private function resolveSimilarIds(Ad $ad): array
    {
        /** @var list<string> $ids */
        $ids = Ad::query()
            ->where('status', AdStatus::ACTIVE->value)
            ->where('category_id', $ad->category_id)
            ->whereKeyNot($ad->id)
            ->orderByDesc('published_at')
            ->limit(self::RESULT_LIMIT)
            ->pluck('id')
            ->all();

        return $ids;
    }

    private function cacheKey(Ad $ad): string
    {
        return sprintf('ads.similar.v1.%s.%s', $ad->id, $ad->category_id);
    }
}
