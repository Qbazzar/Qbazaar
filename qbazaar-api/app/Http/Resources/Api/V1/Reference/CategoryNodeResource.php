<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Reference;

use App\Models\Category;
use App\Services\Catalog\CategoryAdCounts;
use App\Services\Catalog\CategoryFieldPresenter;
use App\Services\Catalog\CategoryTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recursive category tree node — every node carries its already-loaded
 * `children` collection so the consumer can render the full browse
 * hierarchy from a single response.
 *
 * Render nodes from {@see CategoryTree}, which attaches
 * every level of `children` in memory so no depth triggers a query.
 *
 * @mixin Category
 */
class CategoryNodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Render children eagerly as plain arrays so the response can be
        // serialised inside the global `{success, data: ...}` envelope without
        // a Laravel ResourceCollection injecting its own `data` key.
        $children = $this->children->map(
            fn (Category $child): array => (new self($child))->toArray($request),
        )->all();

        $counts = app(CategoryAdCounts::class)->for($this->id);

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'order' => $this->order,
            'is_active' => $this->is_active,
            'custom_fields' => app(CategoryFieldPresenter::class)->present($this->custom_fields, app()->getLocale()),
            'custom_filters' => $this->custom_filters,
            'ads_count' => $counts['ads_count'],
            'today_count' => $counts['today_count'],
            'children' => $children,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
