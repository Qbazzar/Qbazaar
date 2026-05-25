<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\OfferStatus;
use App\Events\Offers\OfferAccepted;
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

    $this->offer = Offer::factory()->pending()->create([
        'conversation_id' => $this->conversation->id,
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
    ]);
});

it('lets the seller accept a pending offer', function (): void {
    Event::fake([OfferAccepted::class]);

    Sanctum::actingAs($this->seller, ['*']);

    postJson('/api/v1/offers/' . $this->offer->id . '/accept')
        ->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->where('data.status', OfferStatus::ACCEPTED->value)
            ->etc());

    expect($this->offer->fresh()->status)->toBe(OfferStatus::ACCEPTED);

    Event::assertDispatched(OfferAccepted::class);
});

it('refuses non-sellers via the policy', function (): void {
    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/offers/' . $this->offer->id . '/accept')
        ->assertStatus(403);
});

it('refuses already-actioned offers', function (): void {
    $this->offer->forceFill([
        'status' => OfferStatus::REJECTED->value,
        'rejected_at' => now(),
    ])->save();

    Sanctum::actingAs($this->seller, ['*']);

    // Status no longer PENDING — policy fails first → 403.
    postJson('/api/v1/offers/' . $this->offer->id . '/accept')
        ->assertStatus(403);
});

it('returns 404 for non-participants', function (): void {
    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger, ['*']);

    postJson('/api/v1/offers/' . $this->offer->id . '/accept')
        ->assertStatus(404);
});
