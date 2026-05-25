<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\OfferStatus;
use App\Events\Offers\OfferExpired;
use App\Jobs\Offers\ExpireOldOffersJob;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

it('flips pending offers past expires_at to EXPIRED and dispatches OfferExpired', function (): void {
    Event::fake([OfferExpired::class]);

    $stale = Offer::factory()->pending()->create([
        'conversation_id' => $this->conversation->id,
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
        'expires_at' => now()->subDay(),
    ]);

    $fresh = Offer::factory()->pending()->create([
        'conversation_id' => $this->conversation->id,
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
        'expires_at' => now()->addDays(3),
    ]);

    (new ExpireOldOffersJob)->handle();

    expect($stale->fresh()->status)->toBe(OfferStatus::EXPIRED)
        ->and($fresh->fresh()->status)->toBe(OfferStatus::PENDING);

    Event::assertDispatched(OfferExpired::class, function (OfferExpired $e) use ($stale): bool {
        return $e->offer->id === $stale->id;
    });

    Event::assertDispatched(OfferExpired::class, 1);
});

it('leaves already-terminal offers untouched', function (): void {
    Event::fake([OfferExpired::class]);

    $accepted = Offer::factory()->accepted()->create([
        'conversation_id' => $this->conversation->id,
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
        'expires_at' => now()->subDay(),
    ]);

    (new ExpireOldOffersJob)->handle();

    expect($accepted->fresh()->status)->toBe(OfferStatus::ACCEPTED);

    Event::assertNotDispatched(OfferExpired::class);
});
