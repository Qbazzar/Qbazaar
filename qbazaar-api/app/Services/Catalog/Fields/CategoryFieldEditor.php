<?php

declare(strict_types=1);

namespace App\Services\Catalog\Fields;

use App\Models\Category;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Catalog\CategoryFields;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Writes a category's own entries in `custom_fields`: field definitions and
 * the markers that hide an inherited field ({@see FieldInheritance}).
 *
 * Each change locks the category row, so two admins editing fields of the
 * same category never overwrite each other, and touches only the entry of
 * the one key it changes: older hand-typed entries are kept as they were.
 * The taxonomy observer flushes the cached schemas once the change commits.
 */
class CategoryFieldEditor
{
    public function __construct(
        private readonly CategoryFields $fields,
        private readonly AdminAuditLogger $audit,
    ) {}

    /**
     * Adds the category's own definition of the field, or replaces the one
     * (or the hidden marker) it already has under that key.
     *
     * @param array<string, mixed> $definition
     */
    public function save(Category $category, array $definition, User $actor): void
    {
        $definition = FieldDefinitions::normalize($definition);

        if ($definition === null || FieldDefinitions::isHidden($definition)) {
            throw new InvalidArgumentException('A field definition needs a valid key.');
        }

        $this->change($category, (string) $definition['key'], $actor, 'admin.categories.fields.saved', static fn (): array => $definition);
    }

    /**
     * Removes the category's own entry for the key: its definition (an
     * inherited one shows again) or its hidden marker (the field returns).
     */
    public function remove(Category $category, string $key, User $actor): bool
    {
        if ($this->ownEntry($category, $key) === null) {
            return false;
        }

        $this->change($category, $key, $actor, 'admin.categories.fields.removed', static fn (): null => null);

        return true;
    }

    /**
     * Hides a field the category inherits, for itself and its subtree.
     * Only an inherited field can be hidden; an own one is removed instead.
     */
    public function hide(Category $category, string $key, User $actor): bool
    {
        $inherited = $this->fields->forCategory($category)->find($key);

        if ($inherited === null || $inherited->isDeclaredBy((string) $category->getKey())) {
            return false;
        }

        $this->change($category, $key, $actor, 'admin.categories.fields.hidden', static fn (): array => ['key' => $key, 'hidden' => true]);

        return true;
    }

    /**
     * The category's own canonical entry for the key, if it has one.
     *
     * @return array<string, mixed>|null
     */
    public function ownEntry(Category $category, string $key): ?array
    {
        foreach (FieldDefinitions::normalizeList($category->custom_fields) as $entry) {
            if ($entry['key'] === $key) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param Closure(): (array<string, mixed>|null) $entry the new entry for the key, null to drop it
     */
    private function change(Category $category, string $key, User $actor, string $event, Closure $entry): void
    {
        DB::transaction(function () use ($category, $key, $actor, $event, $entry): void {
            /** @var Category $locked */
            $locked = Category::query()->whereKey($category->getKey())->lockForUpdate()->firstOrFail();

            $before = $this->ownEntry($locked, $key);
            $after = $entry();
            $stored = $this->replaced(array_values($locked->custom_fields ?? []), $key, $after);

            $locked->custom_fields = $stored === [] ? null : $stored;
            $locked->save();

            $category->custom_fields = $locked->custom_fields;
            $category->syncOriginalAttribute('custom_fields');

            $this->audit->record($actor, $event, $locked, ['key' => $key, 'before' => $before, 'after' => $after]);
        });
    }

    /**
     * @param list<mixed> $stored
     * @param array<string, mixed>|null $entry
     * @return list<mixed>
     */
    private function replaced(array $stored, string $key, ?array $entry): array
    {
        $result = [];
        $placed = false;

        foreach ($stored as $raw) {
            if (! is_array($raw) || ($raw['key'] ?? null) !== $key) {
                $result[] = $raw;

                continue;
            }

            if ($entry !== null && ! $placed) {
                $result[] = $entry;
                $placed = true;
            }
        }

        if ($entry !== null && ! $placed) {
            $result[] = $entry;
        }

        return $result;
    }
}
