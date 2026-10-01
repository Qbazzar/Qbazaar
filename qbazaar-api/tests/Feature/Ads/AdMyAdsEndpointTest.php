<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
});

it('returns the caller\'s ads across every status', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    $other = User::factory()->create();

    $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);
    $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now(),
    ]);
    $this->makeAd($this->seller, ['status' => AdStatus::SOLD->value]);
    $this->makeAd($other, ['status' => AdStatus::ACTIVE->value]);

    $response = getJson('/api/v1/account/ads', [
        'Accept' => 'application/json',
    ])->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

it('filters by status', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    $active = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value, 'published_at' => now()]);
    $this->makeAd($this->seller, ['status' => AdStatus::DRAFT->value]);
    $this->makeAd($this->seller, ['status' => AdStatus::SOLD->value]);

    getJson('/api/v1/account/ads?status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id)
        ->assertJsonPath('meta.total', 1);
});

it('rejects an unknown status', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    getJson('/api/v1/account/ads?status=archived')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('returns the views, favorites and conversation counts of each ad', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    $ad = $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'published_at' => now(),
        'expires_at' => now()->addDays(10),
        'views_count' => 42,
        'favorites_count' => 5,
    ]);
    Conversation::factory()->count(3)->create(['ad_id' => $ad->id, 'seller_id' => $this->seller->id]);

    getJson('/api/v1/account/ads')
        ->assertOk()
        ->assertJsonPath('data.0.views_count', 42)
        ->assertJsonPath('data.0.favorites_count', 5)
        ->assertJsonPath('data.0.conversations_count', 3)
        ->assertJsonPath('data.0.expires_at', $ad->fresh()->expires_at->toIso8601String());
});

it('requires authentication', function (): void {
    getJson('/api/v1/account/ads', [
        'Accept' => 'application/json',
    ])->assertStatus(401);
});
