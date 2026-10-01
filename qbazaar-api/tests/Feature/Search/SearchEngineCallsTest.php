<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $seller = User::factory()->create();
    $this->first = $this->makeAd($seller, ['status' => 'active', 'published_at' => now()]);
    $this->second = $this->makeAd($seller, ['status' => 'active', 'published_at' => now()]);

    // Switched after the ads exist, so creating them never reached the mock.
    config(['scout.driver' => 'meilisearch']);
    $this->index = Mockery::mock(Indexes::class);
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('index')->andReturn($this->index);
    app()->instance(Client::class, $client);
});

it('asks Meilisearch once for hits, total and facets', function (): void {
    $this->index->shouldReceive('rawSearch')
        ->once()
        ->withArgs(fn (string $query, array $options): bool => $query === 'bike'
            && $options['hitsPerPage'] === 2
            && $options['page'] === 1
            && $options['facets'] === ['category_slug', 'location_slug', 'condition', 'price_type']
            && $options['attributesToRetrieve'] === ['id'])
        ->andReturn([
            'hits' => [['id' => $this->second->id], ['id' => 'deleted-since-indexing'], ['id' => $this->first->id]],
            'totalHits' => 7,
            'facetDistribution' => ['condition' => ['used' => 7]],
        ]);

    $response = getJson('/api/v1/search?q=bike&per_page=2')->assertOk();

    expect($response->json('data.*.id'))->toBe([$this->second->id, $this->first->id])
        ->and($response->json('meta.total'))->toBe(7)
        ->and($response->json('meta.last_page'))->toBe(4)
        ->and($response->json('facets.conditions'))->toBe(['used' => 7]);
});

it('hydrates a page of hits with a fixed number of queries', function (): void {
    $this->index->shouldReceive('rawSearch')->andReturn([
        'hits' => [['id' => $this->first->id], ['id' => $this->second->id]],
        'totalHits' => 2,
        'facetDistribution' => [],
    ]);

    DB::enableQueryLog();
    getJson('/api/v1/search')->assertOk();
    $adQueries = collect(DB::getQueryLog())->filter(fn (array $entry): bool => str_contains($entry['query'], 'from "ads"'));

    expect($adQueries)->toHaveCount(1);
});

it('limits search requests with the search rate limiter', function (): void {
    $this->index->shouldReceive('rawSearch')->andReturn(['hits' => [], 'totalHits' => 0]);

    foreach (range(1, 60) as $attempt) {
        getJson('/api/v1/search')->assertOk();
    }

    getJson('/api/v1/search')->assertStatus(429);
});
