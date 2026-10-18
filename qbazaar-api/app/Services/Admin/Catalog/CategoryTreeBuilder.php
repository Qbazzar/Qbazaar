<?php

declare(strict_types=1);

namespace App\Services\Admin\Catalog;

use App\Models\Category;
use App\Services\Catalog\CategoryFields;
use App\Services\Catalog\CategoryHierarchy;
use App\Services\Catalog\Taxonomy\TaxonomyKind;
use App\Services\Catalog\Taxonomy\TaxonomyUsageCounter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use stdClass;

/**
 * The rows of the admin category list: the whole tree in display order, a
 * page of top-level branches at a time, or the categories matching a search.
 *
 * The categories come from one narrow query, the usage counts of the rows
 * on the page from one grouped query per referencing table, and the field
 * counts from the cached schemas, so a page costs the same few queries
 * however large the tree grows.
 */
class CategoryTreeBuilder
{
    public const ROOTS_PER_PAGE = 15;

    public const SEARCH_PER_PAGE = 30;

    private const COLUMNS = ['id', 'parent_id', 'slug', 'name', 'icon', 'order', 'is_active'];

    public function __construct(
        private readonly TaxonomyUsageCounter $usage,
        private readonly CategoryFields $fields,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    /**
     * @return LengthAwarePaginator<int, CategoryTreeRow>
     */
    public function tree(int $page, string $locale): LengthAwarePaginator
    {
        $byParent = [];

        foreach (Category::query()->orderBy('order')->orderBy('id')->toBase()->get(self::COLUMNS) as $node) {
            $byParent[(string) ($node->parent_id ?? '')][] = $node;
        }

        $roots = $byParent[''] ?? [];
        $ordered = [];

        foreach (array_slice($roots, ($page - 1) * self::ROOTS_PER_PAGE, self::ROOTS_PER_PAGE) as $root) {
            $this->walk($root, 0, true, $byParent, $ordered, []);
        }

        return $this->paginator($this->rows($ordered, $locale, []), count($roots), self::ROOTS_PER_PAGE, $page);
    }

    /**
     * Matches the slug or either name. Each row names its parents instead of
     * being indented, since its branch is not on the page.
     *
     * @return LengthAwarePaginator<int, CategoryTreeRow>
     */
    public function search(string $term, int $page, string $locale): LengthAwarePaginator
    {
        $like = '%' . $term . '%';

        $matches = Category::query()
            ->where(fn ($query) => $query->where('slug', 'like', $like)->orWhere('name->ar', 'like', $like)->orWhere('name->en', 'like', $like))
            ->orderBy('order')
            ->orderBy('id')
            ->toBase()
            ->paginate(self::SEARCH_PER_PAGE, self::COLUMNS, 'page', $page);

        $ordered = [];
        $ancestorIds = [];

        foreach ($matches->items() as $node) {
            /** @var stdClass $node */
            $ordered[] = ['node' => $node, 'depth' => 0, 'hiddenByParent' => ! $this->hierarchy->isVisible((string) $node->id) && (bool) $node->is_active];
            array_push($ancestorIds, ...array_slice($this->hierarchy->pathTo((string) $node->id), 0, -1));
        }

        return $this->paginator($this->rows($ordered, $locale, $this->names(array_values(array_unique($ancestorIds)), $locale)), $matches->total(), self::SEARCH_PER_PAGE, $page);
    }

    /**
     * @param array<string, list<stdClass>> $byParent
     * @param list<array{node: stdClass, depth: int, hiddenByParent: bool}> $ordered
     * @param array<string, true> $ancestors guards against a parent cycle saved before cycles were refused
     */
    private function walk(stdClass $node, int $depth, bool $ancestorsActive, array $byParent, array &$ordered, array $ancestors): void
    {
        $id = (string) $node->id;

        if (isset($ancestors[$id])) {
            return;
        }

        $ordered[] = ['node' => $node, 'depth' => $depth, 'hiddenByParent' => ! $ancestorsActive && (bool) $node->is_active];

        foreach ($byParent[$id] ?? [] as $child) {
            $this->walk($child, $depth + 1, $ancestorsActive && (bool) $node->is_active, $byParent, $ordered, $ancestors + [$id => true]);
        }
    }

    /**
     * @param list<array{node: stdClass, depth: int, hiddenByParent: bool}> $ordered
     * @param array<string, string> $ancestorNames names of the parents shown in search results
     * @return list<CategoryTreeRow>
     */
    private function rows(array $ordered, string $locale, array $ancestorNames): array
    {
        $ids = array_map(static fn (array $entry): string => (string) $entry['node']->id, $ordered);
        $usage = $this->usage->count(TaxonomyKind::CATEGORY, $ids);

        return array_map(function (array $entry) use ($usage, $locale, $ancestorNames): CategoryTreeRow {
            $node = $entry['node'];
            $id = (string) $node->id;
            $schema = $this->fields->resolved($id);

            return new CategoryTreeRow(
                id: $id,
                name: $this->localized($node->name, $locale, (string) $node->slug),
                slug: (string) $node->slug,
                icon: is_string($node->icon) && $node->icon !== '' ? $node->icon : null,
                depth: $entry['depth'],
                active: (bool) $node->is_active,
                hiddenByParent: $entry['hiddenByParent'],
                parentPath: $ancestorNames === [] ? null : $this->parentPath($id, $ancestorNames),
                ownFields: $schema->countDeclaredBy($id),
                totalFields: $schema->count(),
                usage: $usage[$id],
            );
        }, $ordered);
    }

    /**
     * @param array<string, string> $names
     */
    private function parentPath(string $id, array $names): ?string
    {
        $parents = array_filter(array_map(
            static fn (string $ancestorId): ?string => $names[$ancestorId] ?? null,
            array_slice($this->hierarchy->pathTo($id), 0, -1),
        ));

        return $parents === [] ? null : implode(' › ', $parents);
    }

    /**
     * @param list<string> $ids
     * @return array<string, string>
     */
    private function names(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        $names = [];

        foreach (Category::query()->whereKey($ids)->toBase()->get(['id', 'slug', 'name']) as $row) {
            $names[(string) $row->id] = $this->localized($row->name, $locale, (string) $row->slug);
        }

        return $names;
    }

    private function localized(mixed $json, string $locale, string $fallback): string
    {
        $name = is_string($json) ? json_decode($json, true) : null;

        return is_array($name) ? (string) ($name[$locale] ?? $name['en'] ?? $fallback) : $fallback;
    }

    /**
     * @param list<CategoryTreeRow> $rows
     * @return LengthAwarePaginator<int, CategoryTreeRow>
     */
    private function paginator(array $rows, int $total, int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator($rows, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }
}
