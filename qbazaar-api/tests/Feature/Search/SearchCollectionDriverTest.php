<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
});

it('runs the default suite on the in-process collection engine', function (): void {
    expect(config('scout.driver'))->toBe('collection');
});

it('indexes and searches ads without a running Meilisearch', function (): void {
    $seller = User::factory()->create();
    $ad = Ad::factory()->active()->create(['user_id' => $seller->id]);

    $ids = Ad::search('')->get()->modelKeys();

    expect($ids)->toContain($ad->getKey());
});

it('rejects invalid sort values via SearchRequest validation', function (): void {
    getJson('/api/v1/search?sort=banana', ['Accept' => 'application/json'])
        ->assertStatus(422);
});

it('returns an empty suggestion list for an empty query', function (): void {
    $response = getJson('/api/v1/search/suggestions?q=', ['Accept' => 'application/json'])
        ->assertOk();

    expect($response->json('data'))->toBe([]);
});
