<?php

declare(strict_types=1);

namespace App\Services\Admin\Catalog;

use App\Services\Catalog\Taxonomy\TaxonomyUsage;

/**
 * One category as the admin tree lists it.
 */
final readonly class CategoryTreeRow
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $icon,
        public int $depth,
        public bool $active,
        public bool $hiddenByParent,
        public ?string $parentPath,
        public int $ownFields,
        public int $totalFields,
        public TaxonomyUsage $usage,
    ) {}

    public function inheritedFields(): int
    {
        return $this->totalFields - $this->ownFields;
    }

    /** Active under an inactive parent still hides from visitors, so it is flagged apart. */
    public function statusTone(): string
    {
        return match (true) {
            ! $this->active => 'neutral',
            $this->hiddenByParent => 'warning',
            default => 'success',
        };
    }

    public function statusLabel(): string
    {
        return (string) __(match (true) {
            ! $this->active => 'admin.catalog.status.inactive',
            $this->hiddenByParent => 'admin.catalog.status.hidden_by_parent',
            default => 'admin.catalog.status.active',
        });
    }
}
