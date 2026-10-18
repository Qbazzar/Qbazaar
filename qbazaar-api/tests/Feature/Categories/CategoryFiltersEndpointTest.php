<?php

declare(strict_types=1);

use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    seed(CategorySeeder::class);
});

it('returns the filters defined for a category', function (): void {
    $response = getJson('/api/v1/categories/cars/filters')->assertOk();

    $data = $response->json('data');
    expect($data)->toBeArray()->not->toBeEmpty();

    // The filters are the fields flagged filterable; price is a filter of every listing, not a category field.
    $keys = collect($data)->pluck('key')->all();
    expect($keys)->toBe(['make', 'year', 'transmission', 'fuel_type']);

    expect(collect($data)->firstWhere('key', 'year')['type'])->toBe('range');

    $make = collect($data)->firstWhere('key', 'make');
    expect($make['label']['ar'])->toBe('الماركة')
        ->and($make['label']['en'])->toBe('Make')
        ->and($make['type'])->toBe('select')
        ->and($make['options'])->toContain('Toyota');
});

it('returns an empty array for a category without custom filters', function (): void {
    getJson('/api/v1/categories/motorcycles/filters')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [],
        ]);
});

it('returns 404 with CATEGORY_NOT_FOUND for an unknown slug', function (): void {
    getJson('/api/v1/categories/does-not-exist/filters')
        ->assertStatus(404)
        ->assertJson(
            fn ($json) => $json
                ->where('success', false)
                ->where('error.code', 'CAT_001')
                ->etc(),
        );
});

it('returns custom fields for a category', function (): void {
    $response = getJson('/api/v1/categories/cars/fields')->assertOk();

    $data = $response->json('data');
    $year = collect($data)->firstWhere('key', 'year');

    expect($year)
        ->not->toBeNull()
        ->and($year['type'])->toBe('number')
        ->and($year['required'])->toBeTrue();
});

it('returns the stats stub for a known category', function (): void {
    getJson('/api/v1/categories/cars/stats')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'ads_count' => 0,
                'sub_ads_count' => 0,
            ],
        ]);
});
