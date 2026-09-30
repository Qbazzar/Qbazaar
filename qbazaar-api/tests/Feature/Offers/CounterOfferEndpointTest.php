<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\MessageType;
use App\Enums\OfferParty;
use App\Enums\OfferStatus;
use App\Enums\PlatformSetting;
use App\Events\Offers\OfferAccepted;
use App\Events\Offers\OfferCountered;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->phoneVerified()->create();
    $this->buyer = User::factory()->phoneVerified()->create();
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
        'amount' => 800,
    ]);
});

function counterOffer(User $actor, Offer $offer, float $amount): TestResponse
{
    Sanctum::actingAs($actor, ['*']);

    return postJson("/api/v1/offers/{$offer->id}/counter", ['amount' => $amount, 'note' => 'Meet me here']);
}

it('lets the seller counter a buyer offer', function (): void {
    Event::fake([OfferCountered::class]);

    $response = counterOffer($this->seller, $this->offer, 950)
        ->assertCreated()
        ->assertJsonPath('data.status', OfferStatus::PENDING->value)
        ->assertJsonPath('data.proposed_by', OfferParty::SELLER->value)
        ->assertJsonPath('data.parent_offer_id', $this->offer->id)
        ->assertJsonPath('data.counter_round', 1)
        ->assertJsonPath('data.amount', '950.00')
        ->assertJsonPath('data.note', 'Meet me here');

    $counter = Offer::query()->findOrFail($response->json('data.id'));

    expect($this->offer->fresh())
        ->status->toBe(OfferStatus::COUNTERED)
        ->countered_at->not->toBeNull();

    expect(Message::query()->findOrFail($counter->message_id))
        ->sender_id->toBe($this->seller->id)
        ->type->toBe(MessageType::OFFER);

    Event::assertDispatched(OfferCountered::class, fn (OfferCountered $event): bool => $event->offer->id === $counter->id
        && $event->otherUserId === $this->buyer->id
        && $event->broadcastAs() === 'offer.countered');
});

it('lets the buyer accept the seller counter-offer', function (): void {
    Event::fake([OfferAccepted::class]);

    $counterId = counterOffer($this->seller, $this->offer, 950)->json('data.id');

    Sanctum::actingAs($this->buyer, ['*']);

    postJson("/api/v1/offers/{$counterId}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', OfferStatus::ACCEPTED->value)
        ->assertJsonPath('data.amount', '950.00');

    Event::assertDispatched(OfferAccepted::class, fn (OfferAccepted $event): bool => $event->otherUserId === $this->seller->id);
});

it('allows one counter per side by default', function (): void {
    $sellerCounterId = counterOffer($this->seller, $this->offer, 950)->json('data.id');
    $buyerCounterId = counterOffer($this->buyer, Offer::query()->findOrFail($sellerCounterId), 870)
        ->assertCreated()
        ->assertJsonPath('data.proposed_by', OfferParty::BUYER->value)
        ->assertJsonPath('data.counter_round', 2)
        ->json('data.id');

    counterOffer($this->seller, Offer::query()->findOrFail($buyerCounterId), 900)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'OFFER_011');

    expect(Offer::query()->findOrFail($buyerCounterId)->status)->toBe(OfferStatus::PENDING);
});

it('reads the round limit from the platform setting', function (): void {
    app(SettingsService::class)->put(PlatformSetting::OFFER_COUNTER_ROUNDS_PER_SIDE, 0, $this->seller);

    counterOffer($this->seller, $this->offer, 950)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'OFFER_011');
});

it('only lets the responder counter', function (): void {
    counterOffer($this->buyer, $this->offer, 950)->assertForbidden();

    expect($this->offer->fresh()->status)->toBe(OfferStatus::PENDING);
});

it('lets the seller withdraw their own counter-offer but not accept it', function (): void {
    $counter = Offer::query()->findOrFail(counterOffer($this->seller, $this->offer, 950)->json('data.id'));

    postJson("/api/v1/offers/{$counter->id}/accept")->assertForbidden();
    postJson("/api/v1/offers/{$counter->id}/withdraw")
        ->assertOk()
        ->assertJsonPath('data.status', OfferStatus::WITHDRAWN->value);
});

it('refuses to counter an offer that is no longer pending', function (): void {
    $this->offer->forceFill(['status' => OfferStatus::REJECTED, 'rejected_at' => now()])->save();

    counterOffer($this->seller, $this->offer, 950)->assertForbidden();
});

it('refuses to counter once the ad is back in review', function (): void {
    $this->ad->forceFill(['status' => AdStatus::PENDING])->save();

    counterOffer($this->seller, $this->offer, 950)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'OFFER_007');
});

it('validates the counter amount', function (): void {
    counterOffer($this->seller, $this->offer, 0)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('returns 404 to non-participants', function (): void {
    counterOffer(User::factory()->phoneVerified()->create(), $this->offer, 950)->assertNotFound();
});
