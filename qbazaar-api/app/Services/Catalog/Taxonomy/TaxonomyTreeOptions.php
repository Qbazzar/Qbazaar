<?php

declare(strict_types=1);

namespace App\Services\Catalog\Taxonomy;

use stdClass;

/**
 * The parent choices of a category or location form, in tree order with
 * their depth, read with one narrow query. The row being edited and its
 * whole subtree are left out, so no choice can create a cycle.
 */
class TaxonomyTreeOptions
{
    /**
     * @return list<array{id: string, label: string, depth: int}>
     */
    public function for(TaxonomyKind $kind, ?string $excludeSubtreeOf, string $locale): array
    {
        $rows = $kind->model()::query()
            ->orderBy('order')
            ->orderBy('id')
            ->toBase()
            ->get(['id', 'parent_id', 'slug', 'name']);

        $byParent = [];

        foreach ($rows as $row) {
            $byParent[(string) ($row->parent_id ?? '')][] = $row;
        }

        $options = [];
        $this->walk($byParent, '', 0, $excludeSubtreeOf, $locale, $options, []);

        return $options;
    }

    /**
     * @param array<string, list<stdClass>> $byParent
     * @param list<array{id: string, label: string, depth: int}> $options
     * @param array<string, true> $ancestors guards against a parent cycle already saved
     */
    private function walk(array $byParent, string $parentId, int $depth, ?string $exclude, string $locale, array &$options, array $ancestors): void
    {
        foreach ($byParent[$parentId] ?? [] as $row) {
            $id = (string) $row->id;

            if ($id === $exclude || isset($ancestors[$id])) {
                continue;
            }

            $options[] = ['id' => $id, 'label' => $this->label($row, $locale), 'depth' => $depth];
            $this->walk($byParent, $id, $depth + 1, $exclude, $locale, $options, $ancestors + [$id => true]);
        }
    }

    private function label(stdClass $row, string $locale): string
    {
        $name = is_string($row->name ?? null) ? json_decode($row->name, true) : null;

        return is_array($name) ? (string) ($name[$locale] ?? $name['en'] ?? $row->slug) : (string) $row->slug;
    }
}
