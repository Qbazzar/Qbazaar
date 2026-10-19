<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\CategoryFieldType;
use App\Models\Category;
use App\Services\Catalog\Fields\FieldInheritance;
use App\Services\Catalog\Fields\ResolvedFields;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\Cache;

/**
 * The effective custom-field schema of any category: its own definitions
 * merged with its ancestors' ({@see FieldInheritance}).
 *
 * Only the categories that declare fields are loaded, in one cached query,
 * and the path comes from the cached {@see CategoryHierarchy}, so resolving
 * any number of categories (a whole tree, a page of listing cards) costs no
 * query once warm. {@see CatalogCache::taxonomyChanged()} flushes it.
 */
#[Scoped]
class CategoryFields
{
    public const CACHE_KEY = 'categories.fields.declared';

    private const TTL_SECONDS = 86_400;

    /** @var array<string, array<mixed>>|null */
    private ?array $declared = null;

    /** @var array<string, ResolvedFields> */
    private array $resolved = [];

    public function __construct(
        private readonly CategoryHierarchy $hierarchy,
        private readonly FieldInheritance $inheritance,
    ) {}

    public function resolved(string $categoryId): ResolvedFields
    {
        return $this->resolved[$categoryId] ??= $this->inheritance->resolve(array_map(
            fn (string $id): array => ['id' => $id, 'fields' => $this->declared()[$id] ?? null],
            $this->hierarchy->pathTo($categoryId),
        ));
    }

    /**
     * Like {@see resolved()}, but the category's own fields are read from the
     * model in hand, so a category that is not saved yet resolves too.
     */
    public function forCategory(Category $category): ResolvedFields
    {
        $id = (string) $category->getKey();
        $own = ['id' => $id, 'fields' => $category->custom_fields];

        if (! $category->exists) {
            return $this->inheritance->resolve([$own]);
        }

        // Read even for a top-level category, so a page of cards costs the
        // same queries whether its categories are nested or not.
        $declared = $this->declared();
        $ancestors = array_slice($this->hierarchy->pathTo($id), 0, -1);

        return $this->inheritance->resolve([
            ...array_map(static fn (string $ancestorId): array => ['id' => $ancestorId, 'fields' => $declared[$ancestorId] ?? null], $ancestors),
            $own,
        ]);
    }

    /**
     * Canonical definitions in display order, for posting and validation.
     *
     * @return list<array<string, mixed>>
     */
    public function effective(string $categoryId): array
    {
        return $this->resolved($categoryId)->definitions();
    }

    /**
     * The search filters of a category: one per filterable field, shaped as
     * the control the field's type calls for.
     *
     * @return list<array<string, mixed>>
     */
    public function filters(string $categoryId): array
    {
        $filters = [];

        foreach ($this->effective($categoryId) as $field) {
            if ($field['filterable'] !== true) {
                continue;
            }

            $filters[] = [
                'key' => $field['key'],
                'label' => $field['label'],
                'type' => CategoryFieldType::fromStored($field['type'])->filterType(),
                'options' => $field['options'],
                'option_labels' => $field['option_labels'],
                'min' => $field['min'],
                'max' => $field['max'],
            ];
        }

        return $filters;
    }

    public function flush(): void
    {
        $this->declared = null;
        $this->resolved = [];
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function declared(): array
    {
        return $this->declared ??= Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, function (): array {
            $declared = [];

            foreach (Category::query()->whereNotNull('custom_fields')->toBase()->get(['id', 'custom_fields']) as $row) {
                $fields = is_string($row->custom_fields) ? json_decode($row->custom_fields, true) : null;

                if (is_array($fields) && $fields !== []) {
                    $declared[(string) $row->id] = $fields;
                }
            }

            return $declared;
        });
    }
}
