<?php

declare(strict_types=1);

namespace App\Services\Catalog\Fields;

/**
 * One field as a category sees it after inheritance: the definition that
 * won, the category that declared it, and whether it replaced a definition
 * of the same key higher up the tree.
 */
final readonly class ResolvedField
{
    /**
     * @param array<string, mixed> $definition canonical, see {@see FieldDefinitions}
     */
    public function __construct(
        public array $definition,
        public string $sourceId,
        public bool $overridesAncestor,
        public int $depth,
        public int $position,
    ) {}

    public function key(): string
    {
        return (string) $this->definition['key'];
    }

    public function sortOrder(): int
    {
        return (int) $this->definition['sort_order'];
    }

    public function isDeclaredBy(string $categoryId): bool
    {
        return $this->sourceId === $categoryId;
    }
}
