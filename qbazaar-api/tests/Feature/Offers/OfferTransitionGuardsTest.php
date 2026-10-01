<?php

declare(strict_types=1);

use App\Actions\Offers\AcceptOfferAction;
use App\Actions\Offers\WithdrawOfferAction;
use App\Enums\AdStatus;
use App\Enums\OfferStatus;
use App\Events\Offers\OfferExpired;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\User;
use App\Services\Ads\AdLifecycleService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->phoneVerified()->create();
    $this->ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
});

function openOfferOn(Ad $ad, User $seller, array $attributes = []): Offer
{
    $buyer = User::factory()->phoneVerified()->create();

    $conversation = Conversation::query()->create([
        'ad_id' => $ad->id,
        'buyer_id' => $buyer->id,
        'seller_id' => $seller->id,
    ]);

    return Offer::factory()->pending()->create([
        'conversation_id' => $conversation->id,
        'ad_id' => $ad->id,
        'buyer_id' => $buyer->id,
        'seller_id' => $seller->id,
        ...$attributes,
    ]);
}

function expectDomainError(callable $callback, ErrorCode $code): void
{
    try {
        $callback();
    } catch (DomainException $exception) {
        expect($exception->errorCode)->toBe($code);

        return;
    }

    test()->fail("Expected {$code->name}.");
}

it('expires the competing open offers when one offer is accepted', function (): void {
    Event::fake([OfferExpired::class]);

    $winner = openOfferOn($this->ad, $this->seller);
    $competitor = openOfferOn($this->ad, $this->seller);
    $otherAdOffer = openOfferOn($this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]), $this->seller);

    Sanctum::actingAs($this->seller, ['*']);

    postJson("/api/v1/offers/{$winner->id}/accept")->assertOk();

    expect($winner->fresh()->status)->toBe(OfferStatus::ACCEPTED)
        ->and($competitor->fresh()->status)->toBe(OfferStatus::EXPIRED)
        ->and($otherAdOffer->fresh()->status)->toBe(OfferStatus::PENDING);

    Event::assertDispatched(OfferExpired::class, fn (OfferExpired $event): bool => $event->offer->id === $competitor->id
        && $event->otherUserId === $competitor->buyer_id);
    Event::assertDispatchedTimes(OfferExpired::class, 1);
});

it('refuses a stale competing offer after another one was accepted', function (): void {
    $first = openOfferOn($this->ad, $this->seller);
    $second = openOfferOn($this->ad, $this->seller);

    $accept = app(AcceptOfferAction::class);
    $accept($this->seller, $first);

    // $second was loaded before the acceptance and still says pending.
    expect($second->status)->toBe(OfferStatus::PENDING);

    expectDomainError(fn () => $accept($this->seller, $second), ErrorCode::OFFER_NOT_PENDING);

    expect(Offer::query()->where('ad_id', $this->ad->id)->where('status', OfferStatus::ACCEPTED->value)->count())->toBe(1);
});

it('lets only one of a racing withdraw and accept win', function (): void {
    $offer = openOfferOn($this->ad, $this->seller);
    $sellerCopy = Offer::query()->findOrFail($offer->id);

    app(WithdrawOfferAction::class)($offer->buyer, $offer);

    expectDomainError(fn () => app(AcceptOfferAction::class)($this->seller, $sellerCopy), ErrorCode::OFFER_NOT_PENDING);

    expect($offer->fresh()->status)->toBe(OfferStatus::WITHDRAWN);
});

it('refuses to accept while the ad is not publicly listed', function (): void {
    $offer = openOfferOn($this->ad, $this->seller);
    $this->ad->forceFill(['status' => AdStatus::PENDING])->save();

    Sanctum::actingAs($this->seller, ['*']);

    postJson("/api/v1/offers/{$offer->id}/accept")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'OFFER_007');

    expect($offer->fresh()->status)->toBe(OfferStatus::PENDING);
});

it('refuses new offers once an offer on the ad was accepted', function (): void {
    openOfferOn($this->ad, $this->seller)->forceFill([
        'status' => OfferStatus::ACCEPTED,
        'accepted_at' => now(),
    ])->save();

    $buyer = User::factory()->phoneVerified()->create();
    $conversation = Conversation::query()->create([
        'ad_id' => $this->ad->id,
        'buyer_id' => $buyer->id,
        'seller_id' => $this->seller->id,
    ]);

    Sanctum::actingAs($buyer, ['*']);

    postJson("/api/v1/conversations/{$conversation->id}/offers", ['amount' => 100])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'OFFER_010');
});

it('refuses offers on a deleted ad', function (): void {
    $buyer = User::factory()->phoneVerified()->create();
    $conversation = Conversation::query()->create([
        'ad_id' => $this->ad->id,
        'buyer_id' => $buyer->id,
        'seller_id' => $this->seller->id,
    ]);
    $this->ad->delete();

    Sanctum::actingAs($buyer, ['*']);

    postJson("/api/v1/conversations/{$conversation->id}/offers", ['amount' => 100])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'OFFER_007');
});

it('replaces an open offer whose window already closed', function (): void {
    $stale = openOfferOn($this->ad, $this->seller, ['expires_at' => now()->subHour()]);

    Sanctum::actingAs($stale->buyer, ['*']);

    postJson("/api/v1/conversations/{$stale->conversation_id}/offers", ['amount' => 100])
        ->assertCreated();

    expect($stale->fresh()->status)->toBe(OfferStatus::EXPIRED);
});

it('enforces one open offer per buyer and ad in the database', function (): void {
    $offer = openOfferOn($this->ad, $this->seller);

    expect(fn () => Offer::factory()->pending()->create([
        'conversation_id' => $offer->conversation_id,
        'ad_id' => $offer->ad_id,
        'buyer_id' => $offer->buyer_id,
        'seller_id' => $offer->seller_id,
    ]))->toThrow(UniqueConstraintViolationException::class);

    $offer->forceFill(['status' => OfferStatus::REJECTED])->save();

    Offer::factory()->pending()->create([
        'conversation_id' => $offer->conversation_id,
        'ad_id' => $offer->ad_id,
        'buyer_id' => $offer->buyer_id,
        'seller_id' => $offer->seller_id,
    ]);

    expect(Offer::query()->where('buyer_id', $offer->buyer_id)->count())->toBe(2);
});

it('closes the open offers when the ad stops being available', function (callable $closeAd): void {
    Event::fake([OfferExpired::class]);

    $open = openOfferOn($this->ad, $this->seller);
    $accepted = openOfferOn($this->ad, $this->seller);
    $accepted->forceFill(['status' => OfferStatus::ACCEPTED, 'accepted_at' => now()])->save();

    $closeAd($this->ad);

    expect($open->fresh()->status)->toBe(OfferStatus::EXPIRED)
        ->and($accepted->fresh()->status)->toBe(OfferStatus::ACCEPTED);

    Event::assertDispatchedTimes(OfferExpired::class, 1);
})->with([
    'sold' => [fn (Ad $ad) => app(AdLifecycleService::class)->markSold($ad)],
    'expired' => [fn (Ad $ad) => app(AdLifecycleService::class)->expire($ad)],
    'blocked' => [fn (Ad $ad) => app(AdLifecycleService::class)->block($ad)],
    'deleted' => [fn (Ad $ad) => $ad->delete()],
]);

it('keeps the open offers while a live ad is back in review', function (): void {
    $open = openOfferOn($this->ad, $this->seller);

    $this->ad->forceFill(['status' => AdStatus::PENDING])->save();

    expect($open->fresh()->status)->toBe(OfferStatus::PENDING);
});
