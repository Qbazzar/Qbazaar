<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    Cache::flush();
});

/**
 * @param array<int, array<string, mixed>> $fields
 * @return array<string, mixed>
 */
function fieldByKey(array $fields, string $key): array
{
    return collect($fields)->firstWhere('key', $key);
}

function carsAd(): Ad
{
    return Ad::factory()->active()->create([
        'user_id' => User::factory()->create()->id,
        'category_id' => Category::query()->where('slug', 'cars')->value('id'),
    ]);
}

it('returns option labels next to the raw option values in the category tree', function (string $locale, array $labels): void {
    $tree = getJson('/api/v1/categories/tree', ['Accept-Language' => $locale])->assertOk()->json('data');
    $vehicles = collect($tree)->firstWhere('slug', 'vehicles');
    $fuel = fieldByKey(collect($vehicles['children'])->firstWhere('slug', 'cars')['custom_fields'], 'fuel_type');

    expect($fuel['options'])->toBe(['petrol', 'diesel', 'hybrid', 'electric'])
        ->and($fuel['options_labeled'])->toBe([
            ['value' => 'petrol', 'label' => $labels[0]],
            ['value' => 'diesel', 'label' => $labels[1]],
            ['value' => 'hybrid', 'label' => $labels[2]],
            ['value' => 'electric', 'label' => $labels[3]],
        ])
        ->and($fuel)->not->toHaveKey('option_labels');
})->with([
    'english' => ['en', ['Petrol', 'Diesel', 'Hybrid', 'Electric']],
    'arabic' => ['ar', ['بنزين', 'ديزل', 'هجين', 'كهرباء']],
]);

it('keeps brand names as they are and gives fields without options null labels', function (): void {
    $tree = getJson('/api/v1/categories/tree', ['Accept-Language' => 'ar'])->assertOk()->json('data');
    $cars = collect(collect($tree)->firstWhere('slug', 'vehicles')['children'])->firstWhere('slug', 'cars');
    $make = collect(fieldByKey($cars['custom_fields'], 'make')['options_labeled'])->pluck('label', 'value');
    $model = fieldByKey($cars['custom_fields'], 'model');

    expect($make['BMW'])->toBe('BMW')
        ->and($make['Other'])->toBe('أخرى')
        ->and($model['options_labeled'])->toBeNull()
        ->and($model['show_in_card'])->toBeFalse();
});

it('labels the options of the fields endpoint and of the ad category', function (): void {
    $fields = getJson('/api/v1/categories/cars/fields', ['Accept-Language' => 'ar'])->assertOk()->json('data');
    $transmission = fieldByKey($fields, 'transmission');

    expect($transmission['options'])->toBe(['automatic', 'manual'])
        ->and($transmission['options_labeled'])->toBe([
            ['value' => 'automatic', 'label' => 'أوتوماتيك'],
            ['value' => 'manual', 'label' => 'عادي'],
        ])
        ->and($transmission['show_in_card'])->toBeTrue()
        ->and(fieldByKey($fields, 'model')['options_labeled'])->toBeNull();

    $detail = getJson('/api/v1/ads/' . carsAd()->id, ['Accept-Language' => 'en'])->assertOk()->json('data.category.custom_fields');

    expect(fieldByKey($detail, 'fuel_type')['options_labeled'][1])->toBe(['value' => 'diesel', 'label' => 'Diesel']);
});

it('falls back to English and then to a readable value when a label is missing', function (): void {
    Category::query()->where('slug', 'cars')->firstOrFail()->update(['custom_fields' => [[
        'key' => 'body',
        'type' => 'select',
        'label' => ['ar' => 'الهيكل', 'en' => 'Body'],
        'options' => ['suv', 'pick_up', 'coupe'],
        'option_labels' => ['suv' => ['en' => 'SUV'], 'coupe' => ['ar' => 'كوبيه', 'en' => 'Coupe']],
    ]]]);

    $body = fieldByKey(getJson('/api/v1/categories/cars/fields', ['Accept-Language' => 'ar'])->json('data'), 'body');

    expect(array_column($body['options_labeled'], 'label'))->toBe(['SUV', 'Pick up', 'كوبيه']);
});

it('keeps the cached ad detail per language', function (): void {
    $id = carsAd()->id;
    $label = fn (string $locale): string => fieldByKey(
        getJson("/api/v1/ads/{$id}", ['Accept-Language' => $locale])->json('data.category.custom_fields'),
        'fuel_type',
    )['options_labeled'][0]['label'];

    expect([$label('en'), $label('ar'), $label('en')])->toBe(['Petrol', 'بنزين', 'Petrol']);
});

it('adds the card flags and labels to categories that already exist', function (): void {
    $migration = require database_path('migrations/2026_10_06_100000_add_card_flags_and_option_labels_to_category_fields.php');
    $cars = Category::query()->where('slug', 'cars')->firstOrFail();
    $legacy = collect($cars->custom_fields)
        ->map(fn (array $field): array => array_diff_key($field, ['show_in_card' => 1, 'option_labels' => 1]))
        ->all();
    $legacy[2]['show_in_card'] = false;
    $cars->update(['custom_fields' => $legacy]);
    $cachedBefore = fieldByKey(getJson('/api/v1/categories/cars/fields')->json('data'), 'transmission');

    $migration->up();

    $fields = Category::query()->where('slug', 'cars')->firstOrFail()->custom_fields;
    $servedAfter = fieldByKey(getJson('/api/v1/categories/cars/fields')->json('data'), 'transmission');
    expect(fieldByKey($fields, 'transmission')['show_in_card'])->toBeTrue()
        ->and(fieldByKey($fields, 'fuel_type')['option_labels']['petrol']['ar'])->toBe('بنزين')
        ->and(fieldByKey($fields, 'year')['show_in_card'])->toBeFalse()
        ->and($cachedBefore['show_in_card'])->toBeFalse()
        ->and($servedAfter['show_in_card'])->toBeTrue();
});
