<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();

    $this->vehicles = Category::query()->where('slug', 'vehicles')->firstOrFail();
    $this->cars = Category::query()->where('slug', 'cars')->firstOrFail();
    $this->sedans = Category::query()->create([
        'parent_id' => $this->cars->id,
        'slug' => 'sedans',
        'name' => ['ar' => 'سيدان', 'en' => 'Sedans'],
        'order' => 0,
    ]);
});

it('returns the category, its parent and one section per active child', function (): void {
    $activeChildren = Category::query()
        ->where('parent_id', $this->vehicles->id)
        ->where('is_active', true)
        ->orderBy('order')
        ->pluck('slug')
        ->all();

    $response = getJson('/api/v1/categories/vehicles')->assertOk();

    expect($response->json('data.category.slug'))->toBe('vehicles')
        ->and($response->json('data.parent'))->toBeNull()
        ->and($response->json('data.sub_category_count'))->toBe(count($activeChildren))
        ->and(collect($response->json('data.sections'))->pluck('category.slug')->all())->toBe($activeChildren);
});

it('includes the parent of a sub-category', function (): void {
    getJson('/api/v1/categories/cars')
        ->assertOk()
        ->assertJsonPath('data.parent.slug', 'vehicles')
        ->assertJsonPath('data.sections.0.category.slug', 'sedans');
});

it('fills each section with the newest listed ads of the child and its descendants', function (): void {
    $newest = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'category_id' => $this->sedans->id,
        'published_at' => now()->subMinute(),
    ]);
    $older = Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'category_id' => $this->cars->id,
        'published_at' => now()->subDay(),
    ]);
    Ad::factory()->create([
        'user_id' => $this->seller->id,
        'category_id' => $this->cars->id,
        'status' => AdStatus::DRAFT->value,
    ]);

    $section = collect(getJson('/api/v1/categories/vehicles')->assertOk()->json('data.sections'))
        ->firstWhere('category.slug', 'cars');

    expect(collect($section['ads'])->pluck('id')->all())->toBe([$newest->id, $older->id])
        ->and($section['category']['ads_count'])->toBe(2);
});

it('caps every section at the configured number of ads', function (): void {
    config(['qbazaar.catalog.category_section_ads' => 2]);

    Ad::factory()->count(2)->active()->create(['user_id' => $this->seller->id, 'category_id' => $this->sedans->id]);
    Ad::factory()->count(2)->active()->create(['user_id' => $this->seller->id, 'category_id' => $this->cars->id]);

    $section = collect(getJson('/api/v1/categories/vehicles')->json('data.sections'))
        ->firstWhere('category.slug', 'cars');

    expect($section['ads'])->toHaveCount(2);
});

it('returns no sections for a leaf category', function (): void {
    getJson('/api/v1/categories/sedans')
        ->assertOk()
        ->assertJsonPath('data.sub_category_count', 0)
        ->assertJsonPath('data.sections', []);
});

it('returns CAT_001 for an unknown category', function (): void {
    getJson('/api/v1/categories/no-such-category')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'CAT_001');
});

it('returns CAT_001 for a category hidden by an inactive ancestor', function (): void {
    $this->cars->update(['is_active' => false]);

    getJson('/api/v1/categories/sedans')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'CAT_001');
});

it('serves repeat visits from the shared cache without querying ads', function (): void {
    Ad::factory()->active()->create(['user_id' => $this->seller->id, 'category_id' => $this->sedans->id]);
    getJson('/api/v1/categories/vehicles')->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    getJson('/api/v1/categories/vehicles')->assertOk();
    DB::disableQueryLog();

    $adQueries = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], '"ads"'));

    expect($adQueries)->toBe([]);
});

it('marks the viewer\'s favourites on the cached cards', function (): void {
    $ad = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'category_id' => $this->sedans->id]);
    getJson('/api/v1/categories/vehicles')->assertOk();

    $viewer = User::factory()->create();
    Favorite::query()->create(['user_id' => $viewer->id, 'ad_id' => $ad->id]);
    Sanctum::actingAs($viewer, ['*']);

    $card = collect(getJson('/api/v1/categories/vehicles')->assertOk()->json('data.sections'))
        ->firstWhere('category.slug', 'cars')['ads'][0];

    expect($card['id'])->toBe($ad->id)
        ->and($card['is_favorited'])->toBeTrue();
});

it('drops the cached pages when the taxonomy changes', function (): void {
    getJson('/api/v1/categories/vehicles')->assertJsonPath('data.category.name.en', $this->vehicles->name['en']);

    $this->vehicles->update(['name' => ['ar' => 'مركبات', 'en' => 'Motors']]);

    getJson('/api/v1/categories/vehicles')->assertJsonPath('data.category.name.en', 'Motors');
});
