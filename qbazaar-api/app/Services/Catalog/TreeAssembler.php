<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Nests a flat list of `parent_id` rows into `children` relations in memory,
 * so a recursive resource renders any depth without lazy loading. Rows whose
 * parent is missing from the list are dropped with their subtree.
 */
class TreeAssembler
{
    /**
     * @template TModel of Model
     *
     * @param Collection<int, TModel> $nodes already in display order
     * @return Collection<int, TModel> the roots
     */
    public function nest(Collection $nodes): Collection
    {
        $byParent = $nodes->groupBy(fn (Model $node): string => (string) $node->getAttribute('parent_id'));

        return $this->attachChildren($byParent, '', []);
    }

    /**
     * @template TModel of Model
     *
     * @param BaseCollection<string, Collection<int, TModel>> $byParent
     * @param array<string, true> $ancestors
     * @return Collection<int, TModel>
     */
    private function attachChildren(BaseCollection $byParent, string $parentId, array $ancestors): Collection
    {
        /** @var Collection<int, TModel> $nodes */
        $nodes = new Collection(($byParent->get($parentId) ?? new Collection)->all());

        foreach ($nodes as $node) {
            $id = (string) $node->getKey();

            // Guards against a parent cycle saved through the admin.
            $children = isset($ancestors[$id])
                ? new Collection
                : $this->attachChildren($byParent, $id, $ancestors + [$id => true]);

            $node->setRelation('children', $children);
        }

        return $nodes;
    }
}
