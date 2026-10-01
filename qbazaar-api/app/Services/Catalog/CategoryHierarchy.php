<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Container\Attributes\Scoped;

#[Scoped]
class CategoryHierarchy extends CachedHierarchy
{
    public function isSelectableLeaf(string $id): bool
    {
        return $this->index()->isSelectableLeaf($id);
    }

    public function isVisible(string $id): bool
    {
        return $this->index()->isVisible($id);
    }

    protected function cacheKey(): string
    {
        return 'categories.hierarchy';
    }

    protected function load(): array
    {
        $rows = Category::query()->toBase()->get(['id', 'parent_id', 'is_active']);

        $parents = [];
        $active = [];

        foreach ($rows as $row) {
            $parents[(string) $row->id] = $row->parent_id !== null ? (string) $row->parent_id : null;
            $active[(string) $row->id] = (bool) $row->is_active;
        }

        return ['parents' => $parents, 'active' => $active];
    }
}
