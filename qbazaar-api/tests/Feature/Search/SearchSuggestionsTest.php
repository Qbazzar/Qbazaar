<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;
use Tests\Concerns\InteractsWithMeilisearch;

uses(RefreshDatabase::class, CreatesAds::class, InteractsWithMeilisearch::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->flushAdsIndex();
});

it('returns prefix-match title suggestions', function (): void {
    Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'title' => 'Vintage Persian rug',
    ]);

    Ad::factory()->active()->create([
        'user_id' => $this->seller->id,
        'title' => 'Modern Persian carpet',
    ]);

    $this->waitForMeilisearch();

    $response = getJson('/api/v1/search/suggestions?q=persi', ['Accept' => 'application/json'])
        ->assertOk();

    $suggestions = $response->json('data');

    expect($suggestions)->toBeArray()
        ->and(count($suggestions))->toBeGreaterThan(0)
        ->and(count($suggestions))->toBeLessThanOrEqual(10);
});

it('returns an empty array for an empty query', function (): void {
    $response = getJson('/api/v1/search/suggestions?q=', ['Accept' => 'application/json'])
        ->assertOk();

    expect($response->json('data'))->toBe([]);
});
