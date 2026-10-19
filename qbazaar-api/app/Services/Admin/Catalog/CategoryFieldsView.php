<?php

declare(strict_types=1);

namespace App\Services\Admin\Catalog;

/**
 * Everything the fields editor shows for one category.
 */
final readonly class CategoryFieldsView
{
    /**
     * @param list<array{name: string, id: string}> $ancestors root first, the category itself left out
     * @param list<CategoryFieldRow> $fields in display order
     * @param list<HiddenFieldRow> $hidden
     */
    public function __construct(
        public array $ancestors,
        public array $fields,
        public array $hidden,
    ) {}

    public function ownCount(): int
    {
        return count(array_filter($this->fields, static fn (CategoryFieldRow $field): bool => $field->isOwn()));
    }

    public function inheritedCount(): int
    {
        return count($this->fields) - $this->ownCount();
    }

    public function hasParent(): bool
    {
        return $this->ancestors !== [];
    }
}
