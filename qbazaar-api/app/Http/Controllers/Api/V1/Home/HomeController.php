<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Home;

use App\Actions\Catalog\GetHomeFeedAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Home\HomeFeedResource;
use App\Services\Catalog\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @group Home
 */
class HomeController extends Controller
{
    /**
     * GET /api/v1/home — every home-screen section in one response.
     *
     * The payload is the same for every visitor, so it is cached whole and
     * flushed by {@see CatalogCache} when listings or the taxonomy change.
     *
     * @unauthenticated
     */
    public function __invoke(Request $request, GetHomeFeedAction $getHomeFeed): JsonResponse
    {
        $payload = Cache::remember(
            CatalogCache::HOME_FEED_KEY,
            (int) config('qbazaar.home.cache_seconds'),
            fn (): array => (new HomeFeedResource($getHomeFeed->execute()))->toArray($request),
        );

        return response()->json($payload);
    }
}
