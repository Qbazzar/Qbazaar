<?php

declare(strict_types=1);

use App\Enums\AdType;
use App\Models\Category;
use App\Models\User;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Exceptions\CommunicationException;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->ad = $this->makeAd(User::factory()->business()->create(), ['status' => 'active', 'published_at' => now()]);

    config(['scout.driver' => 'meilisearch']);
    $this->index = Mockery::mock(Indexes::class);
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('index')->andReturn($this->index);
    app()->instance(Client::class, $client);
});

it('sends the new filters and facet attributes to Meilisearch in one request', function (): void {
    $this->index->shouldReceive('rawSearch')->once()
        ->withArgs(fn (string $q, array $options): bool => $options['filter'] === 'status = "active" AND ad_type = "offering" AND shipping = "delivery" AND seller_type = "business" AND buy_now = true'
            && $options['facets'] === ['category_slug', 'location_slug', 'condition', 'price_type', 'ad_type', 'shipping', 'seller_type', 'buy_now'])
        ->andReturn(['hits' => [['id' => $this->ad->id]], 'totalHits' => 1, 'facetDistribution' => [
            'ad_type' => ['offering' => 1],
            'shipping' => ['delivery' => 1],
            'seller_type' => ['business' => 1],
            'buy_now' => ['true' => 1],
            'condition' => ['used' => 1],
        ]]);

    $response = getJson('/api/v1/search?ad_type=offering&shipping=delivery&seller_type=business&buy_now=true')->assertOk();

    expect($response->json('meta.facets'))->toBe([
        'ad_type' => ['offering' => 1],
        'shipping' => ['delivery' => 1],
        'seller_type' => ['business' => 1],
        'buy_now' => ['true' => 1],
        'condition' => ['used' => 1],
    ])->and($response->json('facets.conditions'))->toBe(['used' => 1]);
});

it('keeps /ads on the database unless it needs the engine', function (): void {
    $this->index->shouldNotReceive('rawSearch');

    getJson('/api/v1/ads?seller_type=business')->assertOk()->assertJsonPath('meta.total', 1);
});

it('sends /ads keywords to the engine with the select facets of the category', function (): void {
    $cars = Category::query()->where('slug', 'cars')->firstOrFail();

    $this->index->shouldReceive('rawSearch')->once()
        ->withArgs(fn (string $q, array $options): bool => $q === 'corolla'
            && $options['facets'] === ['condition', 'ad_type', 'shipping', 'seller_type', 'buy_now', 'custom_fields.make', 'custom_fields.transmission', 'custom_fields.fuel_type']
            && $options['hitsPerPage'] === 20)
        ->andReturn(['hits' => [['id' => $this->ad->id]], 'totalHits' => 1, 'facetDistribution' => [
            'custom_fields.make' => ['Toyota' => 1],
        ]]);

    $response = getJson("/api/v1/ads?q=corolla&category_id={$cars->id}")->assertOk();

    expect($response->json('meta.facets.custom_fields.make'))->toBe(['Toyota' => 1])
        ->and($response->json('meta.facets.custom_fields.transmission'))->toBe([]);
});

it('does not fall back to the database when the engine is down', function (): void {
    $this->index->shouldReceive('rawSearch')->andThrow(new CommunicationException('down'));

    getJson('/api/v1/ads?q=bike')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
});

it('indexes the seller type and the buy now flag', function (): void {
    $document = $this->ad->fresh()->toSearchableArray();

    expect($document['seller_type'])->toBe('business')
        ->and($document['buy_now'])->toBeTrue();

    $this->ad->ad_type = AdType::WANTED;

    expect($this->ad->toSearchableArray()['buy_now'])->toBeFalse();
});

it('declares the new attributes filterable and faceting limits', function (): void {
    $settings = config('scout.meilisearch.index-settings.ads_index');

    expect($settings['filterableAttributes'])->toContain('seller_type', 'buy_now')
        ->and($settings['faceting']['maxValuesPerFacet'])->toBe(100);
});

it('answers an empty page, not a 500, when Meilisearch refuses a filter before the settings are synced', function (): void {
    $body = (string) json_encode(['message' => 'Attribute `seller_type` is not filterable.', 'code' => 'invalid_search_filter', 'type' => 'invalid_request', 'link' => 'https://docs.meilisearch.com']);
    $this->index->shouldReceive('rawSearch')->andThrow(new ApiException(new Response(400), json_decode($body, true)));
    Log::spy();

    getJson('/api/v1/search?seller_type=business&q=secret+words')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Search engine unavailable, returning empty result set'
        && ! str_contains(json_encode($context), 'secret') && strlen((string) $context['query_hash']) === 12)->once();
});

it('logs an error, not a warning, when Meilisearch refuses a request for any other reason', function (): void {
    $this->index->shouldReceive('rawSearch')->andThrow(new ApiException(new Response(401), ['message' => 'The provided API key is invalid.', 'code' => 'invalid_api_key', 'type' => 'auth', 'link' => 'x']));
    Log::spy();

    getJson('/api/v1/search?q=bike')->assertOk()->assertJsonPath('data', []);

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $context['meili_code'] === 'invalid_api_key')->once();
});
