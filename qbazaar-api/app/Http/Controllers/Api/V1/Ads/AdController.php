<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Enums\AdStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Ads\CreateAdRequest;
use App\Http\Requests\Api\V1\Ads\UpdateAdRequest;
use App\Http\Resources\Api\V1\Ads\AdResource;
use App\Http\Resources\Api\V1\Ads\AdSummaryResource;
use App\Models\Ad;
use App\Models\User;
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
    private const PER_PAGE = 20;

    /**
     * GET /api/v1/ads — public feed of active ads, latest first.
     *
     * Optional filters: `category_id`, `location_id`. Both accept the ULID
     * of the corresponding row. Filters are AND-combined.
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Ad::query()
            ->active()
            ->orderedForFeed()
            ->with(['category', 'location', 'media']);

        if (($categoryId = $request->query('category_id')) !== null && is_string($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        if (($locationId = $request->query('location_id')) !== null && is_string($locationId)) {
            $query->where('location_id', $locationId);
        }

        $paginator = $query->paginate(self::PER_PAGE);

        return AdSummaryResource::collection($paginator);
    }

    /**
     * GET /api/v1/ads/{id} — public ad detail.
     *
     * Visibility is governed by AdPolicy::view — public sees ACTIVE / SOLD,
     * owner sees their own drafts. Increments are tracked separately (Sprint 6).
     *
     * @unauthenticated
     *
     * @throws DomainException
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $ad = $this->findAdOrFail($id);

        // This route is public (no `auth:sanctum` middleware), so the DEFAULT
        // guard is `web` and `$request->user()` is null even when a valid
        // Bearer token is present — which hid an owner's own draft behind a
        // 404 (they couldn't open the edit page). Resolve the caller through
        // the `sanctum` guard explicitly so a token-authenticated owner is
        // recognised; the policy still handles the null (truly anonymous) case.
        if (Gate::forUser($request->user('sanctum'))->denies('view', $ad)) {
            // Treat hidden ads (drafts, expired) as "not found" so we
            // don't leak the existence of someone else's draft.
            throw new DomainException(ErrorCode::AD_NOT_FOUND);
        }

        $ad->load(['user', 'category', 'location', 'media']);

        return response()->json((new AdResource($ad))->toArray($request));
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
     * @authenticated
     *
     * @throws DomainException
     */
    public function update(UpdateAdRequest $request, string $id): JsonResponse
    {
        $ad = $this->findAdOrFail($id);
        $this->authorize('update', $ad);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $ad->fill($validated);
        $ad->save();

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
     * GET /api/v1/account/ads — caller's own ads across every status.
     *
     * @authenticated
     */
    public function myAds(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = Ad::query()
            ->forUser($user)
            ->orderByDesc('created_at')
            ->with(['category', 'location', 'media'])
            ->paginate(self::PER_PAGE);

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
