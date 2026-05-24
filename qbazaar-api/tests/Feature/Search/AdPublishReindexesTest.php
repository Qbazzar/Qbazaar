<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;
use Tests\Concerns\InteractsWithMeilisearch;

uses(RefreshDatabase::class, CreatesAds::class, InteractsWithMeilisearch::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
    $this->flushAdsIndex();
});

it('makes a published ad searchable immediately', function (): void {
    $ad = $this->makeAd($this->user, ['status' => AdStatus::DRAFT->value]);

    postJson("/api/v1/ads/{$ad->id}/publish", [], ['Accept' => 'application/json'])
        ->assertOk();

    $this->waitForMeilisearch();

    $response = getJson('/api/v1/search', ['Accept' => 'application/json'])
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($ad->id);
});

it('removes a sold ad from search results', function (): void {
    /** @var Ad $ad */
    $ad = Ad::factory()->active()->create(['user_id' => $this->user->id]);

    $this->waitForMeilisearch();

    // Sanity — appears before sale.
    $ids = collect(getJson('/api/v1/search', ['Accept' => 'application/json'])->json('data'))
        ->pluck('id')->all();
    expect($ids)->toContain($ad->id);

    postJson("/api/v1/ads/{$ad->id}/mark-sold", [], ['Accept' => 'application/json'])
        ->assertOk();

    $this->waitForMeilisearch();

    $ids = collect(getJson('/api/v1/search', ['Accept' => 'application/json'])->json('data'))
        ->pluck('id')->all();

    expect($ids)->not->toContain($ad->id);
});
