<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Reference;

use App\Data\Catalog\CategoryPage;
use App\Http\Resources\Api\V1\Ads\AdSummaryResource;
use App\Models\Ad;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CategoryPage $resource
 */
class CategoryPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $page = $this->resource;

        return [
            'category' => (new CategoryResource($page->category))->toArray($request),
            'parent' => $page->parent !== null ? (new CategoryResource($page->parent))->toArray($request) : null,
            'sub_category_count' => $page->children->count(),
            'sections' => $page->children->map(fn (Category $child): array => [
                'category' => (new CategoryResource($child))->toArray($request),
                'ads' => ($page->adsByChild[$child->id] ?? collect())
                    ->map(fn (Ad $ad): array => (new AdSummaryResource($ad))->toArray($request))
                    ->values()
                    ->all(),
            ])->values()->all(),
        ];
    }
}
