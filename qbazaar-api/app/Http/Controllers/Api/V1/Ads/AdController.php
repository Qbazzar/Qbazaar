<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Actions\Ads\ListOwnAdsAction;
use App\Actions\Ads\ListPublicAdsAction;
use App\Actions\Ads\UpdateAdAction;
use App\Enums\AdStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Ads\CreateAdRequest;
use App\Http\Requests\Api\V1\Ads\ListAdsRequest;
use App\Http\Requests\Api\V1\Ads\ListOwnAdsRequest;
use App\Http\Requests\Api\V1\Ads\UpdateAdRequest;
use App\Http\Resources\Api\V1\Ads\AdResource;
use App\Http\Resources\Api\V1\Ads\AdSummaryResource;
use App\Models\Ad;
use App\Models\User;
use App\Services\Ads\AdDetailCache;
use App\Services\Ads\ViewerFavorites;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Ad CRUD + listing endpoints.
 *
 * Read paths are public; mutations require authentication via the route
 * middleware. Ownership is enforced through AdPolicy + `$this->authorize()`,
 * so this controller stays declarative about who-can-do-what.
 *
 * @group Ads
 */
class AdController extends Controller
{
    /**
     * GET /api/v1/ads — public feed of active ads, latest first.
     *
     * Filters (`category_id`, `location_id` with descendants, price range) are
     * AND-combined. `ids` switches to a lookup that keeps the given order.
     *
     * @unauthenticated
     */
    public function index(ListAdsRequest $request, ListPublicAdsAction $listAds, ViewerFavorites $favorites): AnonymousResourceCollection
    {
        $ids = $request->ids();

        $paginator = $ids !== null ? $listAds->byIds($ids) : $listAds->feed($request->filters());
        $favorites->mark($this->viewer($request), $paginator->items());

        return AdSummaryResource::collection($paginator);
    }

    /**
     * GET /api/v1/ads/{id} — public ad detail.
     *
     * Visibility is governed by AdPolicy::view — public sees ACTIVE / SOLD,
     * owner sees their own drafts. Increments are tracked separately (Sprint 6).
     * Visitors other than the seller share one cached rendering; the seller
     * always gets a fresh one (it carries the private street).
     *
     * @unauthenticated
     *
     * @throws DomainException
     */
    public function show(Request $request, string $id, ViewerFavorites $favorites, AdDetailCache $detailCache): JsonResponse
    {
        $ad = $this->findAdOrFail($id);
        $viewer = $this->viewer($request);

        if (Gate::forUser($viewer)->denies('view', $ad)) {
            // Treat hidden ads (drafts, expired) as "not found" so we
            // don't leak the existence of someone else's draft.
            throw new DomainException(ErrorCode::AD_NOT_FOUND);
        }

        $render = function () use ($ad, $request): array {
            $ad->loadMissing(['user.latestListedAd.location', 'category', 'location', 'media']);

            return (new AdResource($ad))->toArray($request);
        };

        $payload = $viewer?->id === $ad->user_id ? $render() : $detailCache->remember($ad->id, $render);

        return response()->json($favorites->overlay($viewer, [$payload])[0]);
    }

    /**
     * POST /api/v1/ads — create a draft.
     *
     * The ad is created in DRAFT state regardless of any client-supplied
     * status — sellers must explicitly call publish() to go live.
     *
     * @authenticated
     */
    public function store(CreateAdRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $ad = new Ad;
        $ad->fill($validated);
        $ad->user_id = $user->id;
        $ad->status = AdStatus::DRAFT;
        $ad->currency = $validated['currency'] ?? 'QAR';
        $ad->save();

        $ad->load(['user', 'category', 'location', 'media']);

        return response()
            ->json((new AdResource($ad))->toArray($request))
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * PUT /api/v1/ads/{id} — partial update by the owner.
     *
     * Editing the title, description, category or custom fields of an active
     * ad sends it back to pending review.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function update(UpdateAdRequest $request, string $id, UpdateAdAction $updateAd): JsonResponse
    {
        $ad = $this->findAdOrFail($id);
        $this->authorize('update', $ad);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $ad = $updateAd($ad, $validated);

        $ad->load(['user', 'category', 'location', 'media']);

        return response()->json((new AdResource($ad))->toArray($request));
    }

    /**
     * DELETE /api/v1/ads/{id} — soft delete by the owner.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function destroy(Request $request, string $id): Response
    {
        $ad = $this->findAdOrFail($id);
        $this->authorize('delete', $ad);

        $ad->delete();

        return response()->noContent();
    }

    /**
     * GET /api/v1/account/ads — caller's own ads, optionally one status only.
     *
     * @authenticated
     */
    public function myAds(ListOwnAdsRequest $request, ListOwnAdsAction $listOwnAds, ViewerFavorites $favorites): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = $listOwnAds($user, $request->status());
        $favorites->mark($user, $paginator->items());

        return AdSummaryResource::collection($paginator);
    }

    /**
     * Centralised find-or-throw — every endpoint that resolves an ad ID
     * surfaces the same stable AD_NOT_FOUND code on miss.
     *
     * @throws DomainException
     */
    private function findAdOrFail(string $id): Ad
    {
        $ad = Ad::query()->find($id);

        if ($ad === null) {
            throw new DomainException(ErrorCode::AD_NOT_FOUND);
        }

        return $ad;
    }
}
