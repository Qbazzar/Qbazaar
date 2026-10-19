<?php

declare(strict_types=1);

namespace App\Services\Catalog\Fields;

/**
 * The effective field schema of one category: the visible fields in display
 * order, and the inherited fields a category on the path chose to hide.
 */
final readonly class ResolvedFields
{
    /**
     * @param list<ResolvedField> $fields
     * @param array<string, string> $hiddenBy inherited key => id of the category that hid it
     */
    public function __construct(
        public array $fields,
        public array $hiddenBy,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return array_map(static fn (ResolvedField $field): array => $field->definition, $this->fields);
    }

    public function find(string $key): ?ResolvedField
    {
        foreach ($this->fields as $field) {
            if ($field->key() === $key) {
                return $field;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->fields);
    }

    public function countDeclaredBy(string $categoryId): int
    {
        return count(array_filter($this->fields, static fn (ResolvedField $field): bool => $field->isDeclaredBy($categoryId)));
    }
}
