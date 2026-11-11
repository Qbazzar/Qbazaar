<?php

declare(strict_types=1);

namespace App\Services\Catalog\Taxonomy;

use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Catalog\CatalogCache;
use Illuminate\Support\Facades\DB;

/**
 * Deletes categories and locations only when nothing references them
 * ({@see TaxonomyUsage}); otherwise reports what does, so the admin can
 * deactivate instead.
 *
 * The rows are locked while they are checked and deleted. A new ad, child or
 * address referencing a locked row waits on that lock, so nothing can start
 * using a row between its check and its delete.
 */
class TaxonomyDeletion
{
    public function __construct(
        private readonly TaxonomyUsageCounter $counter,
        private readonly CatalogCache $cache,
        private readonly AdminAuditLogger $audit,
    ) {}

    /**
     * Deleting one row fires the model events, so the taxonomy observer
     * flushes the catalog caches once the delete commits. A refused delete
     * changes nothing and writes no audit entry.
     *
     * @return TaxonomyUsage what blocked the delete; an unused usage when the row was deleted
     */
    public function delete(Category|Location $node, User $actor): TaxonomyUsage
    {
        $kind = TaxonomyKind::of($node);
        $id = (string) $node->getKey();

        return DB::transaction(function () use ($kind, $id, $actor): TaxonomyUsage {
            $locked = $kind->model()::query()->whereKey($id)->lockForUpdate()->first();

            if ($locked === null) {
                return new TaxonomyUsage;
            }

            $usage = $this->counter->count($kind, [$id])[$id];

            if (! $usage->inUse()) {
                $locked->delete();

                $this->audit->record($actor, 'admin.' . $kind->routeName() . '.destroy', $locked, [
                    'slug' => $locked->slug,
                    'name' => $locked->name,
                    'parent_id' => $locked->parent_id,
                ]);
            }

            return $usage;
        });
    }

    /**
     * Deletes the unused rows of a selection and reports the others. The
     * audit entry lists both, because the request also flashes an error when
     * part of the selection stayed.
     *
     * A row's children only hold it back when they stay: children selected
     * with it, and free to go themselves, are deleted with it, so a whole
     * branch goes in one selection.
     *
     * @param list<string> $ids
     */
    public function deleteMany(TaxonomyKind $kind, array $ids, User $actor): BulkDeletion
    {
        $result = DB::transaction(function () use ($kind, $ids): BulkDeletion {
            $nodes = $kind->model()::query()->whereKey($ids)->lockForUpdate()->get(['id', 'parent_id', 'slug', 'name']);
            $usage = $this->counter->count($kind, array_values(array_map('strval', $nodes->modelKeys())));

            $selectedChildren = [];
            foreach ($nodes as $node) {
                if ($node->parent_id !== null) {
                    $selectedChildren[(string) $node->parent_id][] = (string) $node->getKey();
                }
            }

            $free = [];
            $blocked = [];
            $verdicts = [];

            foreach ($nodes as $node) {
                $id = (string) $node->getKey();

                if ($this->canDeleteWithSelection($id, $usage, $selectedChildren, $verdicts)) {
                    $free[] = $id;
                } else {
                    $blocked[] = ['id' => $id, 'name' => $node->getLocalizedName(app()->getLocale()), 'usage' => $usage[$id]];
                }
            }

            if ($free !== []) {
                $kind->model()::query()->whereKey($free)->delete();
            }

            return new BulkDeletion($kind, $free, $blocked);
        });

        if ($result->deletedIds !== []) {
            // A query delete fires no model events, so the observer never hears of it.
            $this->cache->taxonomyChanged();

            $this->audit->record($actor, 'admin.' . $kind->routeName() . '.bulk-destroy', null, [
                'deleted' => $result->deletedIds,
                'kept' => array_column($result->blocked, 'id'),
            ]);
        }

        return $result;
    }

    /**
     * Whether a selected row can go: nothing but its children points at it,
     * every child is in the selection, and each of those can go too. The tree
     * is only a few levels deep, and each verdict is computed once.
     *
     * @param array<string, TaxonomyUsage> $usage
     * @param array<string, list<string>> $selectedChildren parent id => ids of its selected children
     * @param array<string, bool> $verdicts
     */
    private function canDeleteWithSelection(string $id, array $usage, array $selectedChildren, array &$verdicts): bool
    {
        if (isset($verdicts[$id])) {
            return $verdicts[$id];
        }

        $children = $selectedChildren[$id] ?? [];
        $free = ! $usage[$id]->inUseBeyondChildren() && $usage[$id]->children === count($children);

        foreach ($children as $child) {
            if (! $this->canDeleteWithSelection($child, $usage, $selectedChildren, $verdicts)) {
                $free = false;
            }
        }

        return $verdicts[$id] = $free;
    }
}
