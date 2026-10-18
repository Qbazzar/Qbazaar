<?php

declare(strict_types=1);

namespace App\Services\Admin\Catalog;

use App\Models\Category;
use App\Services\Catalog\CategoryFields;
use App\Services\Catalog\Fields\CategoryFieldEditor;
use App\Services\Catalog\Fields\FieldDefinitions;

/**
 * Opens the field form of a category in one of its three modes. Null means
 * the field asked for is not one the mode can open.
 */
class CategoryFieldForms
{
    private const SORT_STEP = 10;

    public function __construct(
        private readonly CategoryFields $fields,
        private readonly CategoryFieldEditor $editor,
    ) {}

    /**
     * A new field goes after the ones the category already shows.
     */
    public function blank(Category $category): CategoryFieldForm
    {
        $last = 0;

        foreach ($this->fields->forCategory($category)->fields as $field) {
            $last = max($last, $field->sortOrder());
        }

        return CategoryFieldForm::blank($last + self::SORT_STEP);
    }

    /**
     * An inherited field, prefilled so the category can declare its own version.
     */
    public function override(Category $category, string $key): ?CategoryFieldForm
    {
        $field = $this->fields->forCategory($category)->find($key);

        if ($field === null || $field->isDeclaredBy((string) $category->id)) {
            return null;
        }

        $source = Category::query()->whereKey($field->sourceId)->first(['id', 'slug', 'name']);

        return CategoryFieldForm::fromDefinition(CategoryFieldForm::OVERRIDE, $field->definition, $source?->getLocalizedName(app()->getLocale()));
    }

    public function edit(Category $category, string $key): ?CategoryFieldForm
    {
        $entry = $this->editor->ownEntry($category, $key);

        if ($entry === null || FieldDefinitions::isHidden($entry)) {
            return null;
        }

        return CategoryFieldForm::fromDefinition(CategoryFieldForm::EDIT, $entry);
    }
}
