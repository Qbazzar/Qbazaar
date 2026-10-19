<?php

declare(strict_types=1);

namespace App\Services\Catalog\Taxonomy;

/**
 * What still points at a category or a location. While anything does, the
 * row cannot be deleted: ads would break the foreign key, children would be
 * deleted with it, and saved searches and addresses would silently lose it.
 * Archived (soft-deleted) ads count, because their rows still reference it.
 */
final readonly class TaxonomyUsage
{
    public function __construct(
        public int $ads = 0,
        public int $children = 0,
        public int $savedSearches = 0,
        public int $addresses = 0,
    ) {}

    public function inUse(): bool
    {
        return $this->ads + $this->children + $this->savedSearches + $this->addresses > 0;
    }

    /**
     * One phrase per kind of reference, such as "12 ads", in the app language.
     *
     * @return list<string>
     */
    public function reasons(TaxonomyKind $kind): array
    {
        $counts = [
            'ads' => $this->ads,
            $kind === TaxonomyKind::LOCATION ? 'child_locations' : 'child_categories' => $this->children,
            'saved_searches' => $this->savedSearches,
            'addresses' => $this->addresses,
        ];

        $reasons = [];

        foreach ($counts as $key => $count) {
            if ($count > 0) {
                $reasons[] = trans_choice('admin.catalog.usage.' . $key, $count, ['count' => number_format($count)]);
            }
        }

        return $reasons;
    }

    public function summary(TaxonomyKind $kind): string
    {
        return implode(__('admin.catalog.usage.separator'), $this->reasons($kind));
    }

    /**
     * Why a delete was refused, naming the row and every reference with its count.
     */
    public function blockedMessage(TaxonomyKind $kind, string $name): string
    {
        return __('admin.catalog.delete.blocked_' . $kind->value, ['name' => $name, 'usage' => $this->summary($kind)]);
    }
}
