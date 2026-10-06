<?php

declare(strict_types=1);

use App\Jobs\Search\SyncAdViewCountsJob;
use App\Models\Ad;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Mockery\MockInterface;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
});

it('validates the distance search parameters', function (string $query, string $field): void {
    getJson("/api/v1/search?{$query}")
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => [$field]]]);
})->with([
    'latitude without longitude' => ['lat=25.3', 'lng'],
    'radius without a point' => ['radius_km=5', 'lat'],
    'distance sort without a point' => ['sort=distance', 'lat'],
    'latitude out of range' => ['lat=91&lng=51.5', 'lat'],
    'radius above the cap' => ['lat=25.3&lng=51.5&radius_km=200.5', 'radius_km'],
]);

it('accepts distance and popularity searches and degrades to an empty page without Meilisearch', function (string $query): void {
    getJson("/api/v1/search?{$query}")
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
})->with([
    'radius' => ['lat=25.3&lng=51.5&radius_km=5'],
    'the largest radius the app offers' => ['lat=25.3&lng=51.5&radius_km=200'],
    'distance sort' => ['lat=25.3&lng=51.5&sort=distance'],
    'most viewed' => ['sort=most_viewed'],
]);

it('indexes the ad pin as _geo, falling back to its location centre', function (): void {
    $location = Location::query()->firstOrFail();
    $location->forceFill(['lat' => '25.2854000', 'lng' => '51.5310000'])->save();

    $pinned = $this->makeAd($this->seller, ['location_id' => $location->id, 'latitude' => '25.3000000', 'longitude' => '51.4000000', 'views_count' => 7]);
    $unpinned = $this->makeAd($this->seller, ['location_id' => $location->id]);

    expect($pinned->fresh(['category', 'location'])?->toSearchableArray())
        ->toMatchArray(['_geo' => ['lat' => 25.3, 'lng' => 51.4], 'views_count' => 7])
        ->and($unpinned->fresh(['category', 'location'])?->toSearchableArray()['_geo'])
        ->toBe(['lat' => 25.2854, 'lng' => 51.531]);
});

it('leaves _geo out when neither the ad nor its location has coordinates', function (): void {
    $location = Location::query()->firstOrFail();
    $location->forceFill(['lat' => null, 'lng' => null])->save();

    $ad = $this->makeAd($this->seller, ['location_id' => $location->id]);

    expect($ad->fresh(['category', 'location'])?->toSearchableArray())->not->toHaveKey('_geo');
});

it('declares _geo and views_count in the index settings', function (): void {
    $settings = config('scout.meilisearch.index-settings.ads_index');

    expect($settings['filterableAttributes'])->toContain('_geo')
        ->and($settings['sortableAttributes'])->toContain('_geo', 'views_count');
});

it('pushes recently changed view counters of public ads to the index', function (): void {
    $viewed = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'views_count' => 42]);
    Ad::factory()->active()->create(['user_id' => $this->seller->id, 'updated_at' => now()->subDay()]);
    Ad::factory()->draft()->create(['user_id' => $this->seller->id]);

    config(['scout.driver' => 'meilisearch']);

    $index = Mockery::mock(Indexes::class);
    $index->shouldReceive('updateDocuments')
        ->once()
        ->with([['id' => $viewed->id, 'views_count' => 42]], 'id');

    $this->mock(Client::class, function (MockInterface $client) use ($index): void {
        $client->shouldReceive('index')->once()->with((new Ad)->searchableAs())->andReturn($index);
    });

    app()->call([new SyncAdViewCountsJob, 'handle']);
});

it('does nothing when another search driver is configured', function (): void {
    $this->mock(Client::class, fn (MockInterface $client) => $client->shouldNotReceive('index'));

    app()->call([new SyncAdViewCountsJob, 'handle']);
});
