<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Cache;

/**
 * Loads a tree's `id → parent_id` map once, keeps it in the cache until the
 * taxonomy changes, and memoises it for the current request or job.
 */
abstract class CachedHierarchy
{
    private const TTL_SECONDS = 86_400;

    private ?HierarchyIndex $index = null;

    abstract protected function cacheKey(): string;

    /**
     * @return array{parents: array<string, string|null>, active: array<string, bool>}
     */
    abstract protected function load(): array;

    public function index(): HierarchyIndex
    {
        if ($this->index === null) {
            /** @var array{parents: array<string, string|null>, active: array<string, bool>} $map */
            $map = Cache::remember($this->cacheKey(), self::TTL_SECONDS, fn (): array => $this->load());

            $this->index = new HierarchyIndex($map['parents'], $map['active']);
        }

        return $this->index;
    }

    /**
     * @return list<string>
     */
    public function pathTo(?string $id): array
    {
        if ($id === null) {
            return [];
        }

        return $this->index()->pathTo($id) ?: [$id];
    }

    /**
     * @return list<string>
     */
    public function descendantsOf(string $id): array
    {
        return $this->index()->descendantsOf($id);
    }

    public function flush(): void
    {
        $this->index = null;
        Cache::forget($this->cacheKey());
    }
}
