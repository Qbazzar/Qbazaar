<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Services\Catalog\CategoryHierarchy;

/**
 * Who may act on a category. Only favouriting is a user action: a category
 * can be added to the favourites while visitors can see it (active, under
 * active ancestors). Removing is never restricted, so a category hidden
 * since can always leave the list.
 */
class CategoryPolicy
{
    public function __construct(private readonly CategoryHierarchy $categories) {}

    public function favorite(User $user, Category $category): bool
    {
        return $this->categories->isVisible($category->id);
    }
}
