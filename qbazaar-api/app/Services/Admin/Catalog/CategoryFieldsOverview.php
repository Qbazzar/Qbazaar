<?php

declare(strict_types=1);

namespace App\Services\Admin\Catalog;

use App\Enums\CategoryFieldType;
use App\Models\Category;
use App\Services\Catalog\CategoryFields;
use App\Services\Catalog\CategoryHierarchy;
use App\Services\Catalog\Fields\FieldDefinitions;
use App\Services\Catalog\Fields\ResolvedField;

/**
 * Builds the fields editor of a category: its effective schema with where
 * each field comes from, and the inherited fields it hides.
 *
 * The schema comes from the cached definitions; the names of the categories
 * above it take one query, bounded by the depth of the tree.
 */
class CategoryFieldsOverview
{
    public function __construct(
        private readonly CategoryFields $fields,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    public function for(Category $category, string $locale): CategoryFieldsView
    {
        $id = (string) $category->id;
        $resolved = $this->fields->forCategory($category);
        $ancestorIds = array_slice($this->hierarchy->pathTo($id), 0, -1);
        $names = $this->names([...$ancestorIds, ...array_values($resolved->hiddenBy)], $locale);

        $rows = array_map(fn (ResolvedField $field): CategoryFieldRow => $this->row($field, $id, $names), $resolved->fields);

        return new CategoryFieldsView(
            ancestors: array_map(static fn (string $ancestorId): array => ['id' => $ancestorId, 'name' => $names[$ancestorId] ?? ''], $ancestorIds),
            fields: $rows,
            hidden: $this->hidden($category, $resolved->hiddenBy, $names, $locale),
        );
    }

    /**
     * @param array<string, string> $names
     */
    private function row(ResolvedField $field, string $categoryId, array $names): CategoryFieldRow
    {
        $definition = $field->definition;
        $source = match (true) {
            ! $field->isDeclaredBy($categoryId) => CategoryFieldRow::INHERITED,
            $field->overridesAncestor => CategoryFieldRow::OVERRIDE,
            default => CategoryFieldRow::OWN,
        };

        return new CategoryFieldRow(
            key: $field->key(),
            labelAr: (string) $definition['label']['ar'],
            labelEn: (string) $definition['label']['en'],
            type: CategoryFieldType::fromStored($definition['type']),
            required: $definition['required'] === true,
            filterable: $definition['filterable'] === true,
            showInCard: $definition['show_in_card'] === true,
            sortOrder: $field->sortOrder(),
            optionCount: is_array($definition['options']) ? count($definition['options']) : 0,
            source: $source,
            sourceName: $source === CategoryFieldRow::INHERITED ? ($names[$field->sourceId] ?? null) : null,
        );
    }

    /**
     * Fields hidden on the path, and markers of this category that no longer
     * hide anything (the ancestor dropped the field), so they can be removed.
     *
     * @param array<string, string> $hiddenBy
     * @param array<string, string> $names
     * @return list<HiddenFieldRow>
     */
    private function hidden(Category $category, array $hiddenBy, array $names, string $locale): array
    {
        $id = (string) $category->id;
        $parentLabels = $this->parentLabels($category, $locale);
        $rows = [];

        foreach ($hiddenBy as $key => $byId) {
            $rows[$key] = new HiddenFieldRow((string) $key, $parentLabels[$key] ?? (string) $key, $byId === $id, $byId === $id ? null : ($names[$byId] ?? null));
        }

        foreach (FieldDefinitions::normalizeList($category->custom_fields) as $entry) {
            $key = (string) $entry['key'];

            if (FieldDefinitions::isHidden($entry) && ! isset($rows[$key])) {
                $rows[$key] = new HiddenFieldRow($key, $key, true, null);
            }
        }

        return array_values($rows);
    }

    /**
     * Labels of the fields the parent shows: the ones a hidden marker here can hide.
     *
     * @return array<string, string>
     */
    private function parentLabels(Category $category, string $locale): array
    {
        if ($category->parent_id === null) {
            return [];
        }

        $labels = [];

        foreach ($this->fields->resolved($category->parent_id)->fields as $field) {
            /** @var array{ar: string, en: string} $label */
            $label = $field->definition['label'];
            $labels[$field->key()] = ($label[$locale] ?? '') ?: ($label['en'] ?: $field->key());
        }

        return $labels;
    }

    /**
     * @param list<string> $ids
     * @return array<string, string>
     */
    private function names(array $ids, string $locale): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        return Category::query()
            ->whereKey($ids)
            ->get(['id', 'slug', 'name'])
            ->mapWithKeys(static fn (Category $row): array => [(string) $row->id => $row->getLocalizedName($locale)])
            ->all();
    }
}
