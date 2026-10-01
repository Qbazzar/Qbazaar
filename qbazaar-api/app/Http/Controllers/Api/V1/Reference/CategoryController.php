<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reference;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Reference\CategoryFieldResource;
use App\Http\Resources\Api\V1\Reference\CategoryFilterResource;
use App\Http\Resources\Api\V1\Reference\CategoryNodeResource;
use App\Http\Resources\Api\V1\Reference\CategoryResource;
use App\Models\Category;
use App\Models\User;
use App\Services\Ads\ViewerFavorites;
use App\Services\Catalog\CatalogCache;
use App\Services\Catalog\CategoryAdCounts;
use App\Services\Catalog\CategoryPageCache;
use App\Services\Catalog\CategorySchema;
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
     * The page is the same for every visitor and comes from {@see CategoryPageCache};
     * the viewer's favourite flags are laid over the cached cards.
     *
     * @unauthenticated
     *
     * @throws DomainException
     */
    public function show(Request $request, string $slug, CategoryPageCache $pages, ViewerFavorites $favorites): JsonResponse
    {
        return response()->json($this->withFavorites($pages->get($slug), $this->viewer($request), $favorites));
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
    public function filters(Request $request, string $slug, CategorySchema $schema): JsonResponse
    {
        $data = array_map(
            fn (array $row): array => (new CategoryFilterResource($row))->toArray($request),
            $schema->for($slug)['filters'],
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
    public function fields(Request $request, string $slug, CategorySchema $schema): JsonResponse
    {
        $data = array_map(
            fn (array $row): array => (new CategoryFieldResource($row))->toArray($request),
            $schema->for($slug)['fields'],
        );

        return response()->json($data);
    }

    /**
     * Centralised "find by slug or throw" — keeps the methods that need
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

    /**
     * Lays the viewer's favourite flags over every section's cards with one lookup.
     *
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    private function withFavorites(array $page, ?User $viewer, ViewerFavorites $favorites): array
    {
        /** @var list<array{ads: list<array<string, mixed>>}> $sections */
        $sections = $page['sections'];
        $cards = $favorites->overlay($viewer, array_merge(...array_column($sections, 'ads')));

        $offset = 0;
        foreach ($sections as $index => $section) {
            $count = count($section['ads']);
            $sections[$index]['ads'] = array_slice($cards, $offset, $count);
            $offset += $count;
        }

        $page['sections'] = $sections;

        return $page;
    }
}
