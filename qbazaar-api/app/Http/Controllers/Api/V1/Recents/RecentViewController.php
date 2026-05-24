<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Recents;

use App\Actions\Recents\TrackAdViewAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Ads\AdSummaryResource;
use App\Models\Ad;
use App\Models\RecentView;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Sprint 7 — recently-viewed history.
 *
 *  - `track` records a view for an authenticated user OR an anonymous
 *    client that sends a stable `X-Session-Id` header. The request is
 *    silent-no-op when neither identity is present, so feed prefetchers
 *    that fire-and-forget never receive a 4xx.
 *  - `index` and `destroy` only operate on the authenticated user's
 *    rows. Anonymous histories are not exposed through the API — the
 *    front-end keeps its own local cache for that.
 *
 * @group RecentlyViewed
 */
class RecentViewController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * POST /api/v1/ads/{id}/view — record a view.
     *
     * Returns 204 in every accepted case: success, throttled, or no-identity.
     * We surface AD_NOT_FOUND only when the ad genuinely doesn't exist,
     * to avoid leaking soft-deleted ad ids through 200/404 timing.
     *
     * @unauthenticated
     *
     * @throws DomainException
     */
    public function track(Request $request, TrackAdViewAction $action, string $id): Response
    {
        /** @var Ad|null $ad */
        $ad = Ad::query()->find($id);

        if ($ad === null) {
            throw new DomainException(ErrorCode::AD_NOT_FOUND);
        }

        // The endpoint is public — no `auth:sanctum` middleware. Resolve the
        // bearer-token user manually so logged-in viewers attribute the view
        // to their account while anonymous callers fall back to `X-Session-Id`.
        $resolved = Auth::guard('sanctum')->user();
        $user = $resolved instanceof User ? $resolved : null;

        $sessionId = $this->resolveSessionId($request);

        $action->execute($ad, $user, $sessionId);

        return response()->noContent();
    }

    /**
     * GET /api/v1/account/recently-viewed — paginated list, newest first.
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = RecentView::query()
            ->where('user_id', $user->id)
            ->with(['ad.category', 'ad.location', 'ad.media'])
            ->orderByDesc('viewed_at')
            ->paginate(self::PER_PAGE);

        $items = [];
        foreach ($paginator->items() as $row) {
            /** @var RecentView $row */
            $ad = $row->ad;

            if ($ad === null) {
                continue;
            }

            $payload = (new AdSummaryResource($ad))->toArray($request);
            $payload['viewed_at'] = $row->viewed_at->toIso8601String();

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

    /**
     * DELETE /api/v1/account/recently-viewed — clear the caller's history.
     *
     * @authenticated
     */
    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        RecentView::query()
            ->where('user_id', $user->id)
            ->delete();

        return response()->noContent();
    }

    /**
     * Anon clients identify themselves with `X-Session-Id`. We trim and
     * size-limit defensively — the column caps at 64 chars and we don't
     * want a hostile header to throw at the DB layer.
     */
    private function resolveSessionId(Request $request): ?string
    {
        $header = $request->header('X-Session-Id');

        if (! is_string($header)) {
            return null;
        }

        $value = trim($header);

        if ($value === '' || strlen($value) > 64) {
            return null;
        }

        return $value;
    }
}
