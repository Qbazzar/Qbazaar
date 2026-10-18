<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Container\Attributes\Scoped;

/**
 * The `custom_fields` and `custom_filters` every category payload carries:
 * the effective (inherited) schema in the request language. Both stay null
 * for a category without fields, as they were before inheritance existed.
 */
#[Scoped]
class CategorySchemaPresenter
{
    public function __construct(
        private readonly CategoryFields $fields,
        private readonly CategoryFieldPresenter $presenter,
    ) {}

    /**
     * @return array{custom_fields: list<array<string, mixed>>|null, custom_filters: list<array<string, mixed>>|null}
     */
    public function for(string $categoryId, string $locale): array
    {
        $fields = $this->fields->effective($categoryId);
        $filters = $this->fields->filters($categoryId);

        return [
            'custom_fields' => $fields === [] ? null : array_map(fn (array $field): array => $this->presenter->field($field, $locale), $fields),
            'custom_filters' => $filters === [] ? null : array_map(fn (array $filter): array => $this->presenter->filter($filter, $locale), $filters),
        ];
    }
}
