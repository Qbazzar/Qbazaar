<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Data\Catalog\CategoryPage;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Category;
use App\Services\Catalog\CategoryHierarchy;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds a category landing page: the category, its parent, and one section
 * per active child holding that child's newest ads (its descendants included).
 */
class GetCategoryPageAction
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * @throws DomainException
     */
    public function execute(string $slug): CategoryPage
    {
        $category = $this->findVisibleCategory($slug);

        $children = $category->children()->active()->get();

        return new CategoryPage(
            category: $category,
            parent: $category->parent,
            children: $children,
            adsByChild: $this->newestAdsPerChild($children),
        );
    }

    /**
     * @throws DomainException
     */
    private function findVisibleCategory(string $slug): Category
    {
        $category = Category::query()->with('parent')->where('slug', $slug)->first();

        if ($category === null || ! $this->hierarchy->isVisible($category->id)) {
            throw new DomainException(ErrorCode::CATEGORY_NOT_FOUND);
        }

        return $category;
    }

    /**
     * One windowed query for every section: rank ads inside each leaf category,
     * then keep the newest few per child, since a child's top N always sits
     * within the union of its descendants' top N.
     *
     * @param Collection<int, Category> $children
     * @return array<string, Collection<int, Ad>>
     */
    private function newestAdsPerChild(Collection $children): array
    {
        $childOf = [];
        foreach ($children as $child) {
            foreach ($this->hierarchy->descendantsOf($child->id) as $descendantId) {
                $childOf[$descendantId] = $child->id;
            }
        }

        $sections = array_fill_keys($children->modelKeys(), new Collection);

        if ($childOf === []) {
            return $sections;
        }

        $limit = (int) config('qbazaar.catalog.category_section_ads');

        $ranked = Ad::query()
            ->publiclyListed()
            ->whereIn('category_id', array_keys($childOf))
            ->select('ads.*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY category_id ORDER BY published_at DESC, id DESC) AS category_rank');

        $ads = Ad::query()
            ->fromSub($ranked, 'ads')
            ->where('category_rank', '<=', $limit)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->with(['category', 'location', 'primaryImage'])
            ->get();

        foreach ($ads->groupBy(fn (Ad $ad): string => $childOf[$ad->category_id]) as $childId => $childAds) {
            $sections[$childId] = new Collection($childAds->take($limit)->values()->all());
        }

        return $sections;
    }
}
