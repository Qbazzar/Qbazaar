<?php

declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * In-memory view of a self-referencing tree (categories, locations) built from
 * one `id → parent_id` map, so ancestor and descendant lookups never query.
 */
final class HierarchyIndex
{
    /** @var array<string, list<string>> */
    private array $children = [];

    /**
     * @param array<string, string|null> $parents
     * @param array<string, bool> $active
     */
    public function __construct(
        private readonly array $parents,
        private readonly array $active,
    ) {
        foreach ($parents as $id => $parentId) {
            if ($parentId !== null) {
                $this->children[$parentId][] = (string) $id;
            }
        }
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->parents);
    }

    /**
     * Ancestors from the root down to the node itself.
     *
     * @return list<string>
     */
    public function pathTo(string $id): array
    {
        if (! $this->has($id)) {
            return [];
        }

        $path = [];
        $current = $id;

        // The visited check stops a parent cycle saved through the admin from looping forever.
        while ($current !== null && ! isset($path[$current])) {
            $path[$current] = true;
            $current = $this->parents[$current] ?? null;
        }

        return array_reverse(array_map('strval', array_keys($path)));
    }

    /**
     * The node itself followed by every node below it.
     *
     * @return list<string>
     */
    public function descendantsOf(string $id): array
    {
        if (! $this->has($id)) {
            return [$id];
        }

        $found = [$id => true];
        $queue = [$id];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($this->children[$current] ?? [] as $childId) {
                if (! isset($found[$childId])) {
                    $found[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        return array_map('strval', array_keys($found));
    }

    /**
     * Active and reachable through active ancestors only, i.e. visible in the public tree.
     */
    public function isVisible(string $id): bool
    {
        $path = $this->pathTo($id);

        if ($path === []) {
            return false;
        }

        foreach ($path as $nodeId) {
            if (! ($this->active[$nodeId] ?? true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A visible node with no active children: the only level an ad may be posted in.
     */
    public function isSelectableLeaf(string $id): bool
    {
        if (! $this->isVisible($id)) {
            return false;
        }

        foreach ($this->children[$id] ?? [] as $childId) {
            if ($this->active[$childId] ?? true) {
                return false;
            }
        }

        return true;
    }
}
