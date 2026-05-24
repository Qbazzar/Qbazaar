<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Favorites;

use App\Actions\Favorites\ToggleFavoriteAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Ads\AdSummaryResource;
use App\Models\Ad;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 7 — favourites toggle + listing.
 *
 *  - Toggle is idempotent at the schema layer: the (user_id, ad_id)
 *    unique index in `favorites` guarantees we never store duplicates,
 *    even under a concurrent double-tap. The action wraps the read +
 *    insert/delete in a transaction so the denormalised
 *    `ads.favorites_count` always agrees with the row count.
 *  - Index returns the favourited ad payload (AdSummary shape) plus the
 *    `favorited_at` timestamp from the join, so the frontend can render
 *    "saved on" without a follow-up call.
 *
 * @group Favorites
 */
class FavoriteController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * POST /api/v1/ads/{id}/favorite — toggle the caller's favourite for an ad.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function toggle(Request $request, ToggleFavoriteAction $action, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Ad|null $ad */
        $ad = Ad::query()->find($id);

        if ($ad === null) {
            throw new DomainException(ErrorCode::AD_NOT_FOUND);
        }

        $result = $action->execute($user, $ad);

        return response()->json($result);
    }

    /**
     * GET /api/v1/account/favorites — paginated list of the caller's
     * favourited ads, newest favourite first.
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = Favorite::query()
            ->where('user_id', $user->id)
            ->with(['ad.category', 'ad.location', 'ad.media'])
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE);

        $items = [];
        foreach ($paginator->items() as $favorite) {
            /** @var Favorite $favorite */
            $ad = $favorite->ad;

            // Defensive: cascadeOnDelete should prevent orphan rows, but
            // a soft-deleted ad would still resolve here as null.
            if ($ad === null) {
                continue;
            }

            $payload = (new AdSummaryResource($ad))->toArray($request);
            $payload['favorited_at'] = $favorite->created_at?->toIso8601String();

            $items[] = $payload;
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
