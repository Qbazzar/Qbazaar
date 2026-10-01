<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Favorites;

use App\Actions\Favorites\SetFavoriteAction;
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
        return response()->json($action->execute($this->caller($request), $this->findAdOrFail($id)));
    }

    /**
     * PUT /api/v1/ads/{id}/favorite — idempotent add.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function add(Request $request, SetFavoriteAction $action, string $id): JsonResponse
    {
        return response()->json($action->execute($this->caller($request), $this->findAdOrFail($id), true));
    }

    /**
     * DELETE /api/v1/ads/{id}/favorite — idempotent remove.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function remove(Request $request, SetFavoriteAction $action, string $id): JsonResponse
    {
        // A deleted ad must still leave the list, or it would hold a slot under the cap.
        $ad = Ad::withTrashed()->find($id) ?? throw new DomainException(ErrorCode::AD_NOT_FOUND);

        return response()->json($action->execute($this->caller($request), $ad, false));
    }

    /**
     * GET /api/v1/account/favorites/ids — every favourited ad id, newest
     * first, so clients can paint hearts on any list without a lookup per ad.
     * Bounded by `qbazaar.favorites.max_per_user`.
     *
     * @authenticated
     */
    public function ids(Request $request): JsonResponse
    {
        $ids = Favorite::query()
            ->where('user_id', $this->caller($request)->id)
            ->orderByDesc('created_at')
            ->limit((int) config('qbazaar.favorites.max_per_user'))
            ->pluck('ad_id');

        return response()->json(['ids' => $ids]);
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
            ->with(['ad.category', 'ad.location', 'ad.primaryImage'])
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
            $payload['is_favorited'] = true;
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

    private function caller(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * @throws DomainException
     */
    private function findAdOrFail(string $id): Ad
    {
        return Ad::query()->find($id) ?? throw new DomainException(ErrorCode::AD_NOT_FOUND);
    }
}
