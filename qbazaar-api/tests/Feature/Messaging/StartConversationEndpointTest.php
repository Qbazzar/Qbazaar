<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->buyer = User::factory()->create();
    $this->ad = $this->makeAd($this->seller);
});

it('creates a conversation on first contact and returns 201', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    $response = postJson('/api/v1/conversations', ['ad_id' => $this->ad->id]);

    $response->assertStatus(201)
        ->assertJson(fn ($json) => $json
            ->where('success', true)
            ->where('data.buyer_id', $this->buyer->id)
            ->where('data.seller_id', $this->seller->id)
            ->etc());

    expect(Conversation::query()->count())->toBe(1);
});

it('returns the existing conversation on second call with 200', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations', ['ad_id' => $this->ad->id])->assertStatus(201);
    postJson('/api/v1/conversations', ['ad_id' => $this->ad->id])->assertStatus(200);

    expect(Conversation::query()->count())->toBe(1);
});

it('refuses the ad owner with MSG_CONVERSATION_OWN_AD', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    postJson('/api/v1/conversations', ['ad_id' => $this->ad->id])
        ->assertStatus(422)
        ->assertJson(fn ($json) => $json
            ->where('success', false)
            ->where('error.code', 'MSG_006')
            ->etc());
});

it('refuses when buyer has blocked the seller', function (): void {
    $this->buyer->blockedUsers()->attach($this->seller->id, ['created_at' => now()]);

    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations', ['ad_id' => $this->ad->id])
        ->assertStatus(403)
        ->assertJson(fn ($json) => $json
            ->where('error.code', 'MSG_001')
            ->etc());
});

it('refuses when seller has blocked the buyer', function (): void {
    $this->seller->blockedUsers()->attach($this->buyer->id, ['created_at' => now()]);

    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations', ['ad_id' => $this->ad->id])
        ->assertStatus(403)
        ->assertJson(fn ($json) => $json
            ->where('error.code', 'MSG_001')
            ->etc());
});

it('rejects unauthenticated callers with 401', function (): void {
    postJson('/api/v1/conversations', ['ad_id' => $this->ad->id])->assertStatus(401);
});
