<?php

declare(strict_types=1);

use App\Services\Catalog\CatalogCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Listing-card chips (`show_in_card`) and select labels (`option_labels`)
 * are two optional keys of each custom-field definition. CategorySeeder
 * writes them for fresh installs; this brings the categories already in the
 * database to the same state without a reseed. Keys an admin has already
 * set on a field are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->patchFields(fn (array $field, array $patch): array => $field + $patch);
    }

    /**
     * Removes only the values this migration would have added, so a choice
     * an admin made survives a rollback.
     */
    public function down(): void
    {
        $this->patchFields(fn (array $field, array $patch): array => array_diff_key(
            $field,
            array_filter(
                $patch,
                fn (mixed $value, string $key): bool => $this->sorted($field[$key] ?? null) === $this->sorted($value),
                ARRAY_FILTER_USE_BOTH,
            ),
        ));
    }

    /**
     * MySQL stores JSON objects with their keys reordered.
     */
    private function sorted(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        ksort($value);

        return array_map($this->sorted(...), $value);
    }

    /**
     * @param callable(array<string, mixed>, array<string, mixed>): array<string, mixed> $change
     */
    private function patchFields(callable $change): void
    {
        $touched = false;

        foreach ($this->patches() as $slug => $patchesByKey) {
            $stored = DB::table('categories')->where('slug', $slug)->value('custom_fields');
            $fields = is_string($stored) ? json_decode($stored, true) : null;

            if (! is_array($fields)) {
                continue;
            }

            $patched = array_map(
                fn (mixed $field): mixed => is_array($field) && is_string($field['key'] ?? null) && isset($patchesByKey[$field['key']])
                    ? $change($field, $patchesByKey[$field['key']])
                    : $field,
                $fields,
            );

            DB::table('categories')->where('slug', $slug)->update(['custom_fields' => json_encode($patched, JSON_UNESCAPED_UNICODE)]);
            $touched = true;
        }

        if ($touched) {
            app(CatalogCache::class)->taxonomyChanged();
        }
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function patches(): array
    {
        $other = ['Other' => ['ar' => 'أخرى', 'en' => 'Other']];

        return [
            'cars' => [
                'make' => ['option_labels' => $other],
                'year' => ['show_in_card' => true],
                'mileage_km' => ['show_in_card' => true],
                'transmission' => [
                    'show_in_card' => true,
                    'option_labels' => [
                        'automatic' => ['ar' => 'أوتوماتيك', 'en' => 'Automatic'],
                        'manual' => ['ar' => 'عادي', 'en' => 'Manual'],
                    ],
                ],
                'fuel_type' => ['option_labels' => [
                    'petrol' => ['ar' => 'بنزين', 'en' => 'Petrol'],
                    'diesel' => ['ar' => 'ديزل', 'en' => 'Diesel'],
                    'hybrid' => ['ar' => 'هجين', 'en' => 'Hybrid'],
                    'electric' => ['ar' => 'كهرباء', 'en' => 'Electric'],
                ]],
            ],
            'apartments-for-rent' => [
                'bedrooms' => ['show_in_card' => true],
                'bathrooms' => ['show_in_card' => true],
                'furnished' => [
                    'show_in_card' => true,
                    'option_labels' => [
                        'furnished' => ['ar' => 'مفروش', 'en' => 'Furnished'],
                        'semi_furnished' => ['ar' => 'مفروش جزئياً', 'en' => 'Semi-furnished'],
                        'unfurnished' => ['ar' => 'غير مفروش', 'en' => 'Unfurnished'],
                    ],
                ],
            ],
            'mobile-phones' => [
                'brand' => ['show_in_card' => true, 'option_labels' => $other],
                'storage_gb' => ['show_in_card' => true],
                'condition' => ['option_labels' => [
                    'new' => ['ar' => 'جديد', 'en' => 'New'],
                    'like_new' => ['ar' => 'شبه جديد', 'en' => 'Like new'],
                    'good' => ['ar' => 'جيد', 'en' => 'Good'],
                    'fair' => ['ar' => 'مقبول', 'en' => 'Fair'],
                ]],
            ],
        ];
    }
};
