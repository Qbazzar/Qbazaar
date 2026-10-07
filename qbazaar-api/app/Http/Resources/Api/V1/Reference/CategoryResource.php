<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Reference;

use App\Models\Category;
use App\Services\Catalog\CategoryAdCounts;
use App\Services\Catalog\CategoryFieldPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flat category shape — no children. Used by endpoints that return either
 * a single category (filters, fields, stats) or a flat list (main). The
 * recursive tree shape lives in {@see CategoryNodeResource}.
 *
 * Field order mirrors the `Category` schema in qbazaar-contracts/openapi/v1.yaml.
 *
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
