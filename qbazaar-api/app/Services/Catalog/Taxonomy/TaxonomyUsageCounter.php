<?php

declare(strict_types=1);

namespace App\Services\Catalog\Taxonomy;

use App\Models\Category;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Counts the references to categories and locations. Every count is served
 * by an index on the referencing column, so checking a whole bulk selection
 * or a page of the tree costs the same few queries as checking one row.
 */
class TaxonomyUsageCounter
{
    /**
     * One grouped query per referencing table.
     *
     * @param list<string> $ids
     * @return array<string, TaxonomyUsage> keyed by id, one entry per id asked for
     */
    public function count(TaxonomyKind $kind, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $column = $kind->foreignKey();
        $ads = $this->grouped('ads', $column, $ids);
        $children = $this->grouped($kind->table(), 'parent_id', $ids);
        $savedSearches = $this->grouped('saved_searches', $column, $ids);
        $addresses = $kind->tracksAddresses() ? $this->grouped('user_addresses', $column, $ids) : [];

        $usage = [];

        foreach ($ids as $id) {
            $usage[$id] = new TaxonomyUsage(
                ads: $ads[$id] ?? 0,
                children: $children[$id] ?? 0,
                savedSearches: $savedSearches[$id] ?? 0,
                addresses: $addresses[$id] ?? 0,
            );
        }

        return $usage;
    }

    /**
     * The usage of the row an admin page shows, read as one query of
     * sub-select counts. The counts stay on the model for the page to show.
     */
    public function forNode(Category|Location $node): TaxonomyUsage
    {
        $counts = [
            'children',
            // Archived ads still hold the foreign key.
            'ads' => static fn (Builder $query): Builder => $query->withoutGlobalScopes(),
            'savedSearches',
        ];

        if ($node instanceof Location) {
            $counts[] = 'addresses';
        }

        $node->loadCount($counts);

        return new TaxonomyUsage(
            ads: (int) $node->getAttribute('ads_count'),
            children: (int) $node->getAttribute('children_count'),
            savedSearches: (int) $node->getAttribute('saved_searches_count'),
            addresses: (int) $node->getAttribute('addresses_count'),
        );
    }

    /**
     * Reads the table without model scopes, so archived ads are counted too.
     *
     * @param list<string> $ids
     * @return array<string, int>
     */
    private function grouped(string $table, string $column, array $ids): array
    {
        return DB::table($table)
            ->whereIn($column, $ids)
            ->groupBy($column)
            ->selectRaw('count(*) as aggregate')
            ->addSelect($column)
            ->pluck('aggregate', $column)
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }
}
