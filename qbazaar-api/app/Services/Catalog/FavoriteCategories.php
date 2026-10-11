<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\FavoriteCategory;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Reads of a user's favourite categories: the list for the account screen
 * and the single flag of the category page, each one query on the
 * (user_id, ...) indexes.
 */
class FavoriteCategories
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * Newest first, capped, with the category loaded in the same query set.
     * Categories hidden since they were favourited are left out; the
     * visibility check reads the cached hierarchy, not the database.
     *
     * @return Collection<int, FavoriteCategory>
     */
    public function for(User $user): Collection
    {
        return FavoriteCategory::query()
            ->where('user_id', $user->id)
            ->with('category')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit((int) config('qbazaar.favorites.max_categories_per_user'))
            ->get()
            ->filter(fn (FavoriteCategory $favorite): bool => $this->hierarchy->isVisible($favorite->category_id))
            ->values();
    }

    /**
     * Guests never favourite anything and cost no query.
     */
    public function isFavorited(?User $viewer, string $categoryId): bool
    {
        if ($viewer === null) {
            return false;
        }

        return FavoriteCategory::query()->where('user_id', $viewer->id)->where('category_id', $categoryId)->exists();
    }
}
