<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Home;

use App\Http\Controllers\Controller;
use App\Services\Ads\ViewerFavorites;
use App\Services\Catalog\HomeFeedCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Home
 */
class HomeController extends Controller
{
    /**
     * GET /api/v1/home — every home-screen section in one response.
     *
     * The payload is the same for every visitor of a language, so it is
     * served whole from {@see HomeFeedCache}, which the catalog warmer
     * refreshes every couple of minutes. The viewer's favourite flags are
     * laid over the cached cards.
     *
     * @unauthenticated
     */
    public function __invoke(Request $request, HomeFeedCache $homeFeed, ViewerFavorites $favorites): JsonResponse
    {
        $payload = $homeFeed->get();

        $viewer = $this->viewer($request);
        $payload['recommended'] = $favorites->overlay($viewer, $payload['recommended']);
        $payload['best_selling'] = $favorites->overlay($viewer, $payload['best_selling']);

        return response()->json($payload);
    }
}
