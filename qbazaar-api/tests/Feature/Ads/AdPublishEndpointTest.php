<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->user = User::factory()->create();
});

it('publishes a draft ad and exposes it on the active feed', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    $ad = $this->makeAd($this->user, ['status' => AdStatus::DRAFT->value]);

    $response = postJson("/api/v1/ads/{$ad->id}/publish", [], [
        'Accept' => 'application/json',
    ]);

    $response->assertOk()
        ->assertJson(
            fn ($json) => $json
                ->where('success', true)
                ->where('data.status', AdStatus::ACTIVE->value)
                ->etc(),
        );

    // Now reachable on the public feed.
    getJson('/api/v1/ads', ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.0.id', $ad->id);
});

it('refuses to publish someone else\'s draft', function (): void {
    $intruder = User::factory()->create();
    Sanctum::actingAs($intruder, ['*']);

    $ad = $this->makeAd($this->user, ['status' => AdStatus::DRAFT->value]);

    postJson("/api/v1/ads/{$ad->id}/publish", [], [
        'Accept' => 'application/json',
    ])->assertStatus(403);
});
