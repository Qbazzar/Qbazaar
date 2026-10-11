<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

/*
 * Runs on the database engine (SCOUT_DRIVER=collection), which `/ads` and
 * `/search` share: the same filters must give the same list and the same
 * facet counts on both endpoints.
 */

beforeEach(function (): void {
    config(['qbazaar.search.facets_cache_seconds' => 0]);
    $this->seedReferenceData();
    $this->private = User::factory()->create();
    $this->business = User::factory()->business()->create();

    $listed = ['status' => 'active', 'published_at' => now()];
    $this->offered = $this->makeAd($this->private, [...$listed, 'title' => 'Blue bike', 'ad_type' => 'offering', 'shipping' => 'delivery', 'condition' => 'used']);
    $this->wanted = $this->makeAd($this->private, [...$listed, 'title' => 'Wanted bike', 'ad_type' => 'wanted', 'shipping' => 'pickup_only', 'condition' => 'new']);
    $this->shop = $this->makeAd($this->business, [...$listed, 'title' => 'Shop kettle', 'ad_type' => 'offering', 'shipping' => 'pickup_only', 'condition' => 'new']);
    $this->free = $this->makeAd($this->business, [...$listed, 'title' => 'Free chair', 'price' => null, 'price_type' => 'free', 'condition' => 'used']);
});

dataset('endpoints', ['/api/v1/ads', '/api/v1/search']);

function listedTitles(string $url): array
{
    return collect(getJson($url)->assertOk()->json('data'))->pluck('title')->sort()->values()->all();
}

it('filters by ad type', function (string $endpoint): void {
    expect(listedTitles("$endpoint?ad_type=wanted"))->toBe(['Wanted bike']);
})->with('endpoints');

it('filters by shipping', function (string $endpoint): void {
    expect(listedTitles("$endpoint?shipping=delivery"))->toBe(['Blue bike']);
})->with('endpoints');

it('filters by seller type', function (string $endpoint): void {
    expect(listedTitles("$endpoint?seller_type=business"))->toBe(['Free chair', 'Shop kettle']);
})->with('endpoints');

it('keeps only ads that accept buy now', function (string $endpoint): void {
    expect(listedTitles("$endpoint?buy_now=true"))->toBe(['Blue bike', 'Shop kettle']);
    expect(listedTitles("$endpoint?buy_now=false"))->toHaveCount(4);
})->with('endpoints');

it('stops offering buy now when the stock is gone', function (): void {
    $this->shop->forceFill(['quantity' => 0])->save();

    expect(listedTitles('/api/v1/ads?buy_now=1'))->toBe(['Blue bike']);
});

it('matches a keyword and filters by condition on /ads', function (): void {
    expect(listedTitles('/api/v1/ads?q=bike'))->toBe(['Blue bike', 'Wanted bike'])
        ->and(listedTitles('/api/v1/ads?q=bike&condition=new'))->toBe(['Wanted bike']);
});

it('filters custom fields by equality and range', function (string $endpoint): void {
    $cars = Category::query()->where('slug', 'cars')->firstOrFail();
    $listed = ['status' => 'active', 'published_at' => now(), 'category_id' => $cars->id];
    $this->makeAd($this->private, [...$listed, 'title' => 'Corolla', 'custom_fields' => ['make' => 'Toyota', 'year' => 2018]]);
    $this->makeAd($this->private, [...$listed, 'title' => 'Altima', 'custom_fields' => ['make' => 'Nissan', 'year' => 2012]]);

    expect(listedTitles("$endpoint?custom_fields[make]=Toyota"))->toBe(['Corolla'])
        ->and(listedTitles("$endpoint?custom_fields[year][min]=2015"))->toBe(['Corolla'])
        ->and(listedTitles("$endpoint?custom_fields[year][max]=2015"))->toBe(['Altima']);
})->with('endpoints');

it('counts every filter value in meta.facets', function (string $endpoint): void {
    $facets = getJson($endpoint)->assertOk()->json('meta.facets');

    expect($facets['ad_type'])->toEqual(['offering' => 3, 'wanted' => 1])
        ->and($facets['shipping'])->toEqual(['pickup_only' => 3, 'delivery' => 1])
        ->and($facets['seller_type'])->toEqual(['private' => 2, 'business' => 2])
        ->and($facets['buy_now'])->toEqual(['false' => 2, 'true' => 2])
        ->and($facets['condition'])->toEqual(['used' => 2, 'new' => 2])
        ->and($facets)->not->toHaveKey('custom_fields');
})->with('endpoints');

it('counts within the other active filters', function (string $endpoint): void {
    $facets = getJson("$endpoint?seller_type=business")->assertOk()->json('meta.facets');

    expect($facets['ad_type'])->toBe(['offering' => 2])
        ->and($facets['seller_type'])->toBe(['business' => 2])
        ->and($facets['condition'])->toEqual(['used' => 1, 'new' => 1]);
})->with('endpoints');

it('renders a facet with no match as an empty object', function (): void {
    $response = getJson('/api/v1/ads?ad_type=wanted&seller_type=business')->assertOk();

    expect($response->json('data'))->toBe([])
        ->and($response->getContent())->toContain('"ad_type":{}');
});

it('counts the select options of the chosen category', function (string $endpoint): void {
    $cars = Category::query()->where('slug', 'cars')->firstOrFail();
    $listed = ['status' => 'active', 'published_at' => now(), 'category_id' => $cars->id];
    $this->makeAd($this->private, [...$listed, 'custom_fields' => ['make' => 'Toyota']]);
    $this->makeAd($this->private, [...$listed, 'custom_fields' => ['make' => 'Toyota']]);
    $this->makeAd($this->private, [...$listed, 'custom_fields' => ['make' => 'Nissan']]);

    $facets = getJson("$endpoint?category_id={$cars->id}")->assertOk()->json('meta.facets');

    expect($facets['custom_fields']['make'])->toBe(['Toyota' => 2, 'Nissan' => 1])
        ->and($facets['custom_fields'])->toHaveKeys(['transmission', 'fuel_type']);
})->with('endpoints');

it('keeps the top-level facets of /search', function (): void {
    $facets = getJson('/api/v1/search')->assertOk()->json('facets');

    expect($facets['conditions'])->toEqual(['used' => 2, 'new' => 2])
        ->and($facets['price_types'])->toHaveKeys(['fixed', 'free'])
        ->and($facets['categories'])->not->toBeEmpty();
});

it('rejects values outside the enums and oversized custom filters', function (string $query): void {
    getJson("/api/v1/ads?$query")->assertStatus(422);
    getJson("/api/v1/search?$query")->assertStatus(422);
})->with([
    'ad type' => 'ad_type=swap',
    'shipping' => 'shipping=courier',
    'seller type' => 'seller_type=shop',
    'buy now' => 'buy_now=maybe',
    'condition' => 'condition=broken',
    'long keyword' => 'q=' . str_repeat('a', 201),
    'custom field array value' => 'custom_fields[make][nested][x]=1',
    'range min above max' => 'custom_fields[year][min]=2020&custom_fields[year][max]=2010',
    'range bound not a number' => 'custom_fields[year][min]=abc',
]);

it('shares facet counts per filter set for a short while', function (): void {
    config(['qbazaar.search.facets_cache_seconds' => 60]);

    getJson('/api/v1/ads')->assertOk();
    $this->makeAd($this->private, ['status' => 'active', 'published_at' => now(), 'ad_type' => 'wanted']);

    expect(getJson('/api/v1/ads')->json('meta.facets.ad_type.wanted'))->toBe(1);
});

it('answers the facet counts with a fixed number of queries', function (): void {
    $facetQueries = function (string $url): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        getJson($url)->assertOk();
        DB::disableQueryLog();

        return count(array_filter(DB::getQueryLog(), fn (array $q): bool => str_contains(strtolower($q['query']), 'buy_now__true')));
    };

    // The cache is off here (see beforeEach): one aggregate for every enum facet, however many ads.
    expect($facetQueries('/api/v1/ads'))->toBe(1);

    foreach (range(1, 3) as $_) {
        $this->makeAd($this->private, ['status' => 'active', 'published_at' => now()]);
    }

    expect($facetQueries('/api/v1/ads'))->toBe(1)
        ->and($facetQueries('/api/v1/search'))->toBe(1);
});

it('wraps the reserved condition column in the facet aggregate', function (): void {
    // MariaDB rejects a bare `condition` (a reserved word); SQLite accepts it, so assert the SQL itself.
    DB::enableQueryLog();
    getJson('/api/v1/ads')->assertOk();
    $aggregate = collect(DB::getQueryLog())->pluck('query')->first(fn (string $sql): bool => str_contains($sql, 'buy_now__true'));

    expect($aggregate)->not->toBeNull()
        ->and($aggregate)->toContain(DB::connection()->getQueryGrammar()->wrap('condition') . ' = ?')
        ->and($aggregate)->not->toMatch('/WHEN condition =/');
});
