<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reference;

use App\Actions\Catalog\GetCategoryPageAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Reference\CategoryFieldResource;
use App\Http\Resources\Api\V1\Reference\CategoryFilterResource;
use App\Http\Resources\Api\V1\Reference\CategoryNodeResource;
use App\Http\Resources\Api\V1\Reference\CategoryPageResource;
use App\Http\Resources\Api\V1\Reference\CategoryResource;
use App\Models\Category;
use App\Services\Ads\ViewerFavorites;
use App\Services\Catalog\CatalogCache;
use App\Services\Catalog\CategoryAdCounts;
use App\Services\Catalog\CategoryTree;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only category endpoints used by the browse + search UX.
 *
 * The taxonomy views are cached and flushed by {@see CatalogCache} whenever a
 * category changes; ad counters come from {@see CategoryAdCounts}.
 *
 * @group Reference
 */
class CategoryController extends Controller
{
    private const TREE_TTL = 3600;        // 1 hour

    private const FIELD_TTL = 3600;       // 1 hour

    /**
     * GET /api/v1/categories/tree — full active taxonomy as a nested tree.
     *
     * @unauthenticated
     */
    public function tree(Request $request, CategoryTree $tree): JsonResponse
    {
        $roots = $tree->roots();

        // Map to plain arrays so the global ApiResponseWrapper produces the
        // canonical `{success, data: [...]}` envelope — JsonResource::collection
        // would produce a nested `data.data` shape because we don't paginate here.
        $data = $roots->map(
            fn (Category $node): array => (new CategoryNodeResource($node))->toArray($request),
        )->all();

        return response()->json($data);
    }

    /**
     * GET /api/v1/categories/main — top-level (parent_id IS NULL) only.
     *
     * @unauthenticated
     */
    public function main(Request $request): JsonResponse
    {
        /** @var Collection<int, Category> $roots */
        $roots = Cache::remember(
            CatalogCache::MAIN_CATEGORIES_KEY,
            self::TREE_TTL,
            fn () => Category::query()
                ->whereNull('parent_id')
                ->active()
                ->orderBy('order')
                ->get(),
        );

        $data = $roots->map(
            fn (Category $node): array => (new CategoryResource($node))->toArray($request),
        )->all();

        return response()->json($data);
    }

    /**
     * GET /api/v1/categories/{slug} — category page with a section of newest ads per child.
     *
     * @unauthenticated
     *
     * @throws DomainException
     */
    public function show(Request $request, string $slug, GetCategoryPageAction $getCategoryPage, ViewerFavorites $favorites): JsonResponse
    {
        $page = $getCategoryPage->execute($slug);
        $favorites->mark($this->viewer($request), collect($page->adsByChild)->flatten(1));

        return response()->json((new CategoryPageResource($page))->toArray($request));
    }

    /**
     * GET /api/v1/categories/{slug}/stats — ad counters for the category and its descendants.
     *
     * @unauthenticated
     *
     * @throws DomainException
     */
    public function stats(string $slug, CategoryAdCounts $adCounts): JsonResponse
    {
        $counts = $adCounts->for($this->resolveCategoryOrFail($slug)->id);

        return response()->json([
            'ads_count' => $counts['ads_count'],
            'sub_ads_count' => $counts['ads_count'] - $counts['own_count'],
            'today_count' => $counts['today_count'],
        ]);
    }

    /**
     * GET /api/v1/categories/{slug}/filters — filter definitions used by search.
     *
     * @unauthenticated
     *
     * @throws DomainException
     */
    public function filters(Request $request, string $slug): JsonResponse
    {
        // Validate the slug BEFORE touching the cache so an unknown slug
        // surfaces as a clean 404 even when the cache layer is up.
        $this->resolveCategoryOrFail($slug);

        /** @var array<int, array<string, mixed>> $filters */
        $filters = Cache::remember(
            "categories.filters.{$slug}",
            self::FIELD_TTL,
            fn (): array => $this->resolveCategoryOrFail($slug)->custom_filters ?? [],
        );

        $data = array_map(
            fn (array $row): array => (new CategoryFilterResource($row))->toArray($request),
            $filters,
        );

        return response()->json($data);
    }

    /**
     * GET /api/v1/categories/{slug}/fields — custom-field definitions for ad posting.
     *
     * @unauthenticated
     *
     * @throws DomainException
     */
    public function fields(Request $request, string $slug): JsonResponse
    {
        $this->resolveCategoryOrFail($slug);

        /** @var array<int, array<string, mixed>> $fields */
        $fields = Cache::remember(
            "categories.fields.{$slug}",
            self::FIELD_TTL,
            fn (): array => $this->resolveCategoryOrFail($slug)->custom_fields ?? [],
        );

        $data = array_map(
            fn (array $row): array => (new CategoryFieldResource($row))->toArray($request),
            $fields,
        );

        return response()->json($data);
    }

    /**
     * Centralised "find by slug or throw" — keeps the four methods that need
     * it from duplicating the not-found branch and ensures every miss surfaces
     * the same stable CATEGORY_NOT_FOUND code.
     *
     * @throws DomainException
     */
    private function resolveCategoryOrFail(string $slug): Category
    {
        /** @var Category|null $category */
        $category = Category::query()->where('slug', $slug)->first();

        if ($category === null) {
            throw new DomainException(ErrorCode::CATEGORY_NOT_FOUND);
        }

        return $category;
    }
}
