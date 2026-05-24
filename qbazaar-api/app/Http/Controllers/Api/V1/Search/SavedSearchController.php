<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Search;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Search\SaveSearchRequest;
use App\Http\Resources\Api\V1\Search\SavedSearchResource;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Sprint 6 — per-user saved searches.
 *
 * All endpoints require an authenticated, active user via the route group.
 * Ownership is implied: a user can only see / mutate rows where
 * `user_id = auth()->id()`. We never expose the column in URLs.
 *
 * @group Search
 */
class SavedSearchController extends Controller
{
    /** Hard cap per user; the spec calls for 10. */
    private const MAX_PER_USER = 10;

    /**
     * GET /api/v1/account/saved-searches — caller's own saved searches.
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rows = SavedSearch::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $data = $rows
            ->map(fn (SavedSearch $row): array => (new SavedSearchResource($row))->toArray($request))
            ->all();

        return response()->json($data);
    }

    /**
     * POST /api/v1/account/saved-searches — store a new saved search.
     *
     * Enforces the per-user cap before insert so we never persist past the
     * limit and then have to clean up. Returns SEARCH_SAVED_LIMIT (422)
     * once the cap is reached.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function store(SaveSearchRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = SavedSearch::query()
            ->where('user_id', $user->id)
            ->count();

        if ($count >= self::MAX_PER_USER) {
            throw new DomainException(ErrorCode::SEARCH_SAVED_LIMIT);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $row = new SavedSearch;
        $row->fill($validated);
        $row->user_id = $user->id;
        $row->save();

        return response()
            ->json((new SavedSearchResource($row))->toArray($request))
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * DELETE /api/v1/account/saved-searches/{id} — remove one of the caller's.
     *
     * Missing rows surface as SEARCH_SAVED_NOT_FOUND (404) so we don't leak
     * the existence of another user's saved search via a 403 vs 404 oracle.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function destroy(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $request->user();

        /** @var SavedSearch|null $row */
        $row = SavedSearch::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if ($row === null) {
            throw new DomainException(ErrorCode::SEARCH_SAVED_NOT_FOUND);
        }

        $row->delete();

        return response()->noContent();
    }
}
