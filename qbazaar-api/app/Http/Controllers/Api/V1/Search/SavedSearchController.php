<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Search;

use App\Actions\Search\SaveSearchAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Search\PatchSavedSearchRequest;
use App\Http\Requests\Api\V1\Search\SaveSearchRequest;
use App\Http\Resources\Api\V1\Search\SavedSearchResource;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Per-user saved searches.
 *
 * Ownership is implied: a user only ever sees or changes rows where
 * `user_id` is their own, and someone else's id answers 404, never 403.
 *
 * @group Search
 */
class SavedSearchController extends Controller
{
    public function __construct(private readonly SaveSearchAction $saveSearch) {}

    /**
     * GET /api/v1/account/saved-searches — the caller's saved searches,
     * bounded by `qbazaar.search.saved_search_max_per_user`.
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        $rows = SavedSearch::query()
            ->where('user_id', $this->caller($request)->id)
            ->orderByDesc('created_at')
            ->limit((int) config('qbazaar.search.saved_search_max_per_user'))
            ->get();

        return response()->json(SavedSearchResource::collection($rows)->toArray($request));
    }

    /**
     * POST /api/v1/account/saved-searches — SEARCH_SAVED_LIMIT (422) once
     * the cap is reached.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function store(SaveSearchRequest $request): JsonResponse
    {
        $search = $this->saveSearch->create($this->caller($request), $request->savedSearch());

        return response()
            ->json((new SavedSearchResource($search))->toArray($request))
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * PUT /api/v1/account/saved-searches/{id} — replace name and filters.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function update(SaveSearchRequest $request, string $id): JsonResponse
    {
        $search = $this->saveSearch->update($this->caller($request), $id, $request->savedSearch());

        return response()->json((new SavedSearchResource($search))->toArray($request));
    }

    /**
     * PATCH /api/v1/account/saved-searches/{id} — switch alerts or rename.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function patch(PatchSavedSearchRequest $request, string $id): JsonResponse
    {
        $search = $this->saveSearch->update($this->caller($request), $id, $request->changes());

        return response()->json((new SavedSearchResource($search))->toArray($request));
    }

    /**
     * DELETE /api/v1/account/saved-searches/{id}
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function destroy(Request $request, string $id): Response
    {
        $deleted = SavedSearch::query()
            ->where('user_id', $this->caller($request)->id)
            ->whereKey($id)
            ->delete();

        if ($deleted === 0) {
            throw new DomainException(ErrorCode::SEARCH_SAVED_NOT_FOUND);
        }

        return response()->noContent();
    }

    private function caller(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
