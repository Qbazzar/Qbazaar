<?php

declare(strict_types=1);

use App\Services\Catalog\CatalogCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A category's search filters used to be a second hand-typed list,
 * `custom_filters`. They are now the custom fields flagged `filterable`, so
 * every field already listed as a filter gets the flag and keeps its filter
 * (and its facet counts). Fields where an admin already chose a value are
 * left alone. `custom_filters` itself is no longer read and is kept as it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->patch(static fn (array $field): array => $field + ['filterable' => true]);
    }

    /**
     * Removes only the flag this migration could have added.
     */
    public function down(): void
    {
        $this->patch(static function (array $field): array {
            if (($field['filterable'] ?? null) === true) {
                unset($field['filterable']);
            }

            return $field;
        });
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $change applied to each field listed as a filter
     */
    private function patch(callable $change): void
    {
        $touched = false;

        DB::table('categories')
            ->whereNotNull('custom_fields')
            ->whereNotNull('custom_filters')
            ->select(['id', 'custom_fields', 'custom_filters'])
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($change, &$touched): void {
                foreach ($rows as $row) {
                    $fields = $this->decode($row->custom_fields);
                    $filterKeys = array_column(array_filter($this->decode($row->custom_filters), 'is_array'), 'key');

                    if ($fields === [] || $filterKeys === []) {
                        continue;
                    }

                    $patched = array_map(
                        static fn (mixed $field): mixed => is_array($field) && in_array($field['key'] ?? null, $filterKeys, true) ? $change($field) : $field,
                        $fields,
                    );

                    if ($patched !== $fields) {
                        DB::table('categories')->where('id', $row->id)->update(['custom_fields' => json_encode($patched, JSON_UNESCAPED_UNICODE)]);
                        $touched = true;
                    }
                }
            });

        if ($touched) {
            app(CatalogCache::class)->taxonomyChanged();
        }
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decode(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
};
