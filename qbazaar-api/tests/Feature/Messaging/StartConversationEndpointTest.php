<?php

declare(strict_types=1);

use App\Data\Account\PrivacySettings;
use App\Enums\AdStatus;
use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->phoneVerified()->create();
    $this->buyer = User::factory()->phoneVerified()->create();
    $this->ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
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

it('refuses a new conversation on an ad that is not active', function (AdStatus $status): void {
    $this->ad->forceFill(['status' => $status])->save();

    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations', ['ad_id' => $this->ad->id])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'AD_002');

    expect(Conversation::query()->count())->toBe(0);
})->with([AdStatus::DRAFT, AdStatus::PENDING, AdStatus::SOLD, AdStatus::BLOCKED]);

it('refuses a new conversation when the seller is not active', function (UserStatus $status): void {
    $this->seller->forceFill(['status' => $status])->save();

    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations', ['ad_id' => $this->ad->id])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'AD_002');

    expect(Conversation::query()->count())->toBe(0);
})->with([UserStatus::SUSPENDED, UserStatus::DEACTIVATED]);

it('refuses a new conversation when the seller has turned chat off', function (): void {
    $this->seller->forceFill(['privacy_settings' => new PrivacySettings(allow_chat: false)])->save();

    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations', ['ad_id' => $this->ad->id])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'MSG_008');

    expect(Conversation::query()->count())->toBe(0);
});

it('still returns an existing conversation after the ad is sold', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations', ['ad_id' => $this->ad->id])->assertStatus(201);

    $this->ad->forceFill(['status' => AdStatus::SOLD])->save();

    postJson('/api/v1/conversations', ['ad_id' => $this->ad->id])->assertStatus(200);
});
