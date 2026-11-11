<?php

declare(strict_types=1);

namespace App\Services\Catalog\Taxonomy;

use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;

/**
 * Creates and edits categories and locations from the admin panel, writing
 * one audit entry per change that names the row and what changed (from →
 * to), as the bulk delete does. Saving goes through the model, so the
 * taxonomy observer still drops the catalog caches.
 */
class TaxonomyEditor
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(User $actor, TaxonomyKind $kind, array $attributes): Category|Location
    {
        $node = $kind->model()::query()->create($attributes);

        $this->audit->record($actor, $this->event($kind, 'store'), $node, ['attributes' => $attributes]);

        return $node;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(User $actor, Category|Location $node, array $attributes): void
    {
        $this->save($actor, $node, $attributes, 'update');
    }

    public function setActive(User $actor, Category|Location $node, bool $active): void
    {
        $this->save($actor, $node, ['is_active' => $active], 'status');
    }

    /**
     * A form saved unchanged writes no detailed entry; the audit middleware
     * still records the request itself.
     *
     * @param array<string, mixed> $attributes
     */
    private function save(User $actor, Category|Location $node, array $attributes, string $action): void
    {
        $node->fill($attributes);

        $changes = [];
        foreach (array_keys($node->getDirty()) as $key) {
            $changes[$key] = ['from' => $node->getOriginal($key), 'to' => $node->getAttribute($key)];
        }

        if ($changes === []) {
            return;
        }

        $node->save();

        $this->audit->record($actor, $this->event(TaxonomyKind::of($node), $action), $node, ['changes' => $changes]);
    }

    private function event(TaxonomyKind $kind, string $action): string
    {
        return 'admin.' . $kind->routeName() . '.' . $action;
    }
}
