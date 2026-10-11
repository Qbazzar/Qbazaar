<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Account;

use App\Models\FavoriteCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lean row of the favourite-categories list: just what a chip needs. The
 * category must be loaded; lists never resolve it row by row.
 *
 * @mixin FavoriteCategory
 */
class FavoriteCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $category = $this->category;

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->name,
            'icon' => $category->icon,
            'favorited_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
