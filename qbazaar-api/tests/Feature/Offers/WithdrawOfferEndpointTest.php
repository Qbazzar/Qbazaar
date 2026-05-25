<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\OfferStatus;
use App\Events\Offers\OfferWithdrawn;
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

it('lets the buyer withdraw a pending offer', function (): void {
    Event::fake([OfferWithdrawn::class]);

    Sanctum::actingAs($this->buyer, ['*']);

    postJson('/api/v1/offers/' . $this->offer->id . '/withdraw')
        ->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->where('data.status', OfferStatus::WITHDRAWN->value)
            ->etc());

    expect($this->offer->fresh()->status)->toBe(OfferStatus::WITHDRAWN);

    Event::assertDispatched(OfferWithdrawn::class);
});

it('refuses the seller from withdrawing the offer', function (): void {
    Sanctum::actingAs($this->seller, ['*']);

    postJson('/api/v1/offers/' . $this->offer->id . '/withdraw')
        ->assertStatus(403);
});
