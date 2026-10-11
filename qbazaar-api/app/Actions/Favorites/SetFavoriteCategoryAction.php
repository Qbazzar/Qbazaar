<?php

declare(strict_types=1);

namespace App\Actions\Favorites;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Category;
use App\Models\FavoriteCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Puts a category in or out of the user's favourites. Repeating either call
 * is a no-op: the (user_id, category_id) unique key decides whether a row was
 * really added, so a concurrent double tap cannot duplicate it, and the
 * user row lock keeps parallel adds from slipping past the cap together.
 */
class SetFavoriteCategoryAction
{
    public function execute(User $user, Category $category, bool $favorited): void
    {
        DB::transaction(function () use ($user, $category, $favorited): void {
            $favorited ? $this->add($user, $category) : $this->remove($user, $category);
        });
    }

    private function add(User $user, Category $category): void
    {
        if (FavoriteCategory::query()->where('user_id', $user->id)->where('category_id', $category->id)->exists()) {
            return;
        }

        User::query()->whereKey($user->id)->lockForUpdate()->first(['id']);

        if (FavoriteCategory::query()->where('user_id', $user->id)->count() >= (int) config('qbazaar.favorites.max_categories_per_user')) {
            throw new DomainException(ErrorCode::FAV_CATEGORY_LIMIT_REACHED);
        }

        FavoriteCategory::query()->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'category_id' => $category->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function remove(User $user, Category $category): void
    {
        FavoriteCategory::query()->where('user_id', $user->id)->where('category_id', $category->id)->delete();
    }
}
