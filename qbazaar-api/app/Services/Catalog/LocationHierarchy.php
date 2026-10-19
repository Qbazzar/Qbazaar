<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Location;
use Illuminate\Container\Attributes\Scoped;

#[Scoped]
class LocationHierarchy extends CachedHierarchy
{
    protected function cacheKey(): string
    {
        return 'locations.hierarchy';
    }

    protected function load(): array
    {
        $parents = [];
        $active = [];

        foreach (Location::query()->toBase()->get(['id', 'parent_id', 'is_active']) as $row) {
            $parents[(string) $row->id] = $row->parent_id !== null ? (string) $row->parent_id : null;
            $active[(string) $row->id] = (bool) $row->is_active;
        }

        return ['parents' => $parents, 'active' => $active];
    }
}
