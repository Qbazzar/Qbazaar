<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Favorites;

use App\Actions\Favorites\SetFavoriteCategoryAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\SetFavoriteCategoryRequest;
use App\Http\Resources\Api\V1\Account\FavoriteCategoryResource;
use App\Models\Category;
use App\Models\User;
use App\Services\Catalog\FavoriteCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The "Add to Favorite" pill of a category page: list, add and remove the
 * caller's favourite categories. Add and remove are idempotent.
 *
 * @group Favorites
 */
class FavoriteCategoryController extends Controller
{
    /**
     * GET /api/v1/account/favorite-categories — newest first, capped.
     *
     * @authenticated
     */
    public function index(Request $request, FavoriteCategories $favorites): JsonResponse
    {
        // resolve() gives the plain list; the response wrapper adds the envelope.
        return response()->json(FavoriteCategoryResource::collection($favorites->for($this->caller($request)))->resolve($request));
    }

    /**
     * PUT /api/v1/account/favorite-categories/{category} — idempotent add.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function add(SetFavoriteCategoryRequest $request, SetFavoriteCategoryAction $action): JsonResponse
    {
        $user = $this->caller($request);
        $category = $this->findCategory($request->slug());

        // A hidden category answers like a missing one, so its existence does not leak.
        if (Gate::forUser($user)->denies('favorite', $category)) {
            throw new DomainException(ErrorCode::CATEGORY_NOT_FOUND);
        }

        $action->execute($user, $category, true);

        return response()->json(['favorited' => true]);
    }

    /**
     * DELETE /api/v1/account/favorite-categories/{category} — idempotent remove.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function remove(SetFavoriteCategoryRequest $request, SetFavoriteCategoryAction $action): JsonResponse
    {
        $action->execute($this->caller($request), $this->findCategory($request->slug()), false);

        return response()->json(['favorited' => false]);
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
    private function findCategory(string $slug): Category
    {
        return Category::query()->where('slug', $slug)->first() ?? throw new DomainException(ErrorCode::CATEGORY_NOT_FOUND);
    }
}
