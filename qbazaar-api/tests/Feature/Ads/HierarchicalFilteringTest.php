<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Services\Search\AdSearchCriteria;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('includes ads of every descendant when listing a category', function (): void {
    $inGrandchild = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'category_id' => $this->sedans->id]);
    $inChild = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'category_id' => $this->cars->id]);
    Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'category_id' => Category::query()->where('slug', 'furniture')->value('id'),
    ]);

    $ids = collect(getJson("/api/v1/ads?category_id={$this->vehicles->id}")->assertOk()->json('data'))->pluck('id');

    expect($ids->all())->toEqualCanonicalizing([$inGrandchild->id, $inChild->id]);
});

it('includes ads of every district when listing a city', function (): void {
    $city = Location::query()->whereNull('parent_id')->whereHas('children')->firstOrFail();
    $district = Location::query()->where('parent_id', $city->id)->firstOrFail();
    $elsewhere = Location::query()->whereNull('parent_id')->whereKeyNot($city->id)->firstOrFail();

    $inDistrict = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'location_id' => $district->id]);
    Ad::factory()->active()->create(['user_id' => $this->seller->id, 'location_id' => $elsewhere->id]);

    $ids = collect(getJson("/api/v1/ads?location_id={$city->id}")->assertOk()->json('data'))->pluck('id');

    expect($ids->all())->toBe([$inDistrict->id]);
});

it('indexes the category and location ancestry for search', function (): void {
    $ad = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'category_id' => $this->sedans->id]);
    $location = $ad->location()->firstOrFail();

    $document = $ad->toSearchableArray();

    expect($document['category_path'])->toBe([$this->vehicles->id, $this->cars->id, $this->sedans->id])
        ->and($document['location_path'])->toBe(array_values(array_filter([$location->parent_id, $location->id])));
});

it('filters search by ancestry so a grandparent matches its grandchildren', function (): void {
    $filter = (new AdSearchCriteria)->filter([
        'category_id' => $this->vehicles->id,
        'location_id' => '01JABCDEFGHJKMNPQRSTVWXYZ0',
    ]);

    expect($filter)
        ->toContain(sprintf('category_path = "%s"', $this->vehicles->id))
        ->toContain('location_path = "01JABCDEFGHJKMNPQRSTVWXYZ0"')
        ->not->toContain('category_id =');
});

it('declares the ancestry attributes as filterable in the index settings', function (): void {
    expect(config('scout.meilisearch.index-settings.ads_index.filterableAttributes'))
        ->toContain('category_path', 'location_path');
});
