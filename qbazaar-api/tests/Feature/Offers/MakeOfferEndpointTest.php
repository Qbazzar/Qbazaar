<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\OfferStatus;
use App\Events\Offers\OfferCreated;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->buyer = User::factory()->create();
    $this->ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
    $this->conversation = Conversation::query()->create([
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
    ]);
});

it('creates a pending offer and links it to a chat message', function (): void {
    Event::fake([OfferCreated::class]);

    Sanctum::actingAs($this->buyer, ['*']);

    $response = postJson('/api/v1/conversations/' . $this->conversation->id . '/offers', [
        'amount' => 1500,
        'note' => 'Can we meet halfway?',
    ]);

    $response->assertStatus(201)
        ->assertJson(fn ($json) => $json
            ->where('success', true)
            ->where('data.status', OfferStatus::PENDING->value)
            ->where('data.buyer_id', $this->buyer->id)
            ->where('data.seller_id', $this->seller->id)
            ->where('data.amount', '1500.00')
            ->where('data.note', 'Can we meet halfway?')
            ->etc());

    expect(Offer::query()->count())->toBe(1);

    $offer = Offer::query()->first();
    expect($offer->message_id)->not->toBeNull();

    Event::assertDispatched(OfferCreated::class);
});

it('refuses an offer on your own ad', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/offers', [
        'amount' => 100,
    ])->assertStatus(422)
        ->assertJson(fn ($json) => $json->where('error.code', 'OFFER_006')->etc());
});

it('refuses when buyer has a blocking relationship', function (): void {
    $this->buyer->blockedUsers()->attach($this->seller->id, ['created_at' => now()]);

    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/offers', [
        'amount' => 100,
    ])->assertStatus(403)
        ->assertJson(fn ($json) => $json->where('error.code', 'MSG_001')->etc());
});

it('refuses a second active offer on the same ad', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/offers', [
        'amount' => 100,
    ])->assertStatus(201);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/offers', [
        'amount' => 200,
    ])->assertStatus(422)
        ->assertJson(fn ($json) => $json->where('error.code', 'OFFER_005')->etc());
});

it('refuses offers on non-active ads', function (): void {
    $this->ad->forceFill(['status' => AdStatus::SOLD->value])->save();

    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/offers', [
        'amount' => 100,
    ])->assertStatus(422)
        ->assertJson(fn ($json) => $json->where('error.code', 'OFFER_007')->etc());
});

it('returns 404 for non-participants to avoid oracle leak', function (): void {
    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger, ['*']);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/offers', [
        'amount' => 100,
    ])->assertStatus(404);
});

it('validates amount lower bound', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/conversations/' . $this->conversation->id . '/offers', [
        'amount' => 0,
    ])->assertStatus(422);
});
