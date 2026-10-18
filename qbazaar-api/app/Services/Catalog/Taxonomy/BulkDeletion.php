<?php

declare(strict_types=1);

namespace App\Services\Catalog\Taxonomy;

/**
 * The result of a bulk delete: the rows that went, and the rows that stayed
 * because something still references them.
 */
final readonly class BulkDeletion
{
    private const NAMED_LIMIT = 5;

    /**
     * @param list<string> $deletedIds
     * @param list<array{id: string, name: string, usage: TaxonomyUsage}> $blocked
     */
    public function __construct(
        public TaxonomyKind $kind,
        public array $deletedIds,
        public array $blocked,
    ) {}

    public function deletedCount(): int
    {
        return count($this->deletedIds);
    }

    public function hasBlocked(): bool
    {
        return $this->blocked !== [];
    }

    /**
     * The flash message: the count deleted, then the first few rows kept and why.
     */
    public function message(): string
    {
        $deleted = $this->deletedCount();
        $message = trans_choice('admin.catalog.delete.bulk_deleted', $deleted, ['count' => number_format($deleted)]);

        if (! $this->hasBlocked()) {
            return $message;
        }

        $named = array_map(
            fn (array $row): string => __('admin.catalog.delete.bulk_blocked_item', ['name' => $row['name'], 'usage' => $row['usage']->summary($this->kind)]),
            array_slice($this->blocked, 0, self::NAMED_LIMIT),
        );
        $more = count($this->blocked) - count($named);

        return $message . ' ' . __('admin.catalog.delete.bulk_blocked', [
            'items' => implode(__('admin.catalog.usage.list_separator'), $named),
        ]) . ($more > 0 ? ' ' . trans_choice('admin.catalog.delete.bulk_blocked_more', $more, ['count' => number_format($more)]) : '');
    }
}
