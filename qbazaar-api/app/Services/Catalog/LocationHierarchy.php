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
        $parents = Location::query()
            ->toBase()
            ->pluck('parent_id', 'id')
            ->map(fn (mixed $parentId): ?string => $parentId !== null ? (string) $parentId : null)
            ->all();

        /** @var array<string, string|null> $parents */
        return ['parents' => $parents, 'active' => []];
    }
}
