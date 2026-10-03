<?php

declare(strict_types=1);

use App\Actions\Offers\AcceptOfferAction;
use App\Data\Ledger\LedgerActor;
use App\Enums\AdStatus;
use App\Enums\OfferStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PlatformSetting;
use App\Events\Orders\OrderCreated;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Category;
use App\Models\CategoryCommissionRate;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use App\Services\Ledger\LedgerRecipes;
use App\Services\Orders\CommissionRates;
use App\Services\Orders\OrderPlacementService;
use App\Services\Settings\SettingsService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesAds;
use Tests\Concerns\PlacesOrders;

uses(RefreshDatabase::class, CreatesAds::class, PlacesOrders::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->buyer = User::factory()->create();
    $this->admin = User::factory()->create();
    config(['qbazaar.commission.rate' => '5.00', 'qbazaar.commission.debt_ceiling' => '100.00']);
});

function chargeDebt(User $seller, string $commission): void
{
    app(LedgerRecipes::class)->chargeCommission(
        Order::factory()->make(['id' => (string) Str::ulid(), 'ad_id' => null, 'buyer_id' => null, 'seller_id' => $seller->id, 'commission_amount' => $commission]),
        LedgerActor::system(),
    );
}

it('turns an accepted offer into an order with the price and commission frozen', function (): void {
    Event::fake([OrderCreated::class]);
    $offer = $this->pendingOffer($this->seller, $this->buyer, amount: '1999.99');

    $order = $this->acceptOffer($offer);

    expect($order->status)->toBe(OrderStatus::CREATED)
        ->and($order->source)->toBe(OrderSource::OFFER)
        ->and($order->buyer_id)->toBe($this->buyer->id)
        ->and($order->seller_id)->toBe($this->seller->id)
        ->and($order->unit_price)->toBe('1999.99')
        ->and($order->quantity)->toBe(1)
        ->and($order->total)->toBe('1999.99')
        ->and($order->commission_rate)->toBe('5.00')
        ->and($order->commission_amount)->toBe('100.00')
        ->and($order->active_ad_id)->toBe($offer->ad_id)
        ->and($order->ad_title)->toBe($offer->ad->title)
        ->and(Ad::query()->find($offer->ad_id)->isReserved())->toBeTrue();

    Event::assertDispatched(OrderCreated::class, fn (OrderCreated $event): bool => $event->order->is($order));
});

it('rounds the commission half-up to the cent', function (): void {
    config(['qbazaar.commission.rate' => '2.50']);

    // 2.5% of 10.10 is 0.2525 and of 10.30 is 0.2575.
    $low = $this->placeOrder($this->seller, $this->buyer, '10.10');
    $high = $this->placeOrder($this->seller, User::factory()->create(), '10.30');

    expect($low->commission_amount)->toBe('0.25')
        ->and($high->commission_amount)->toBe('0.26');
});

it('uses the nearest category override up the parent chain, then the general rate', function (): void {
    $parent = Category::query()->whereNull('parent_id')->whereHas('children')->firstOrFail();
    $child = $parent->children()->firstOrFail();
    $other = Category::query()->whereNull('parent_id')->whereKeyNot($parent->id)->firstOrFail();

    CategoryCommissionRate::query()->create(['category_id' => $parent->id, 'rate' => '8.00']);

    $fromParent = $this->placeOrder($this->seller, $this->buyer, '100.00', $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value, 'category_id' => $child->id]));
    $general = $this->placeOrder($this->seller, $this->buyer, '100.00', $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value, 'category_id' => $other->id]));

    CategoryCommissionRate::query()->create(['category_id' => $child->id, 'rate' => '3.00']);
    app(CommissionRates::class)->forget();
    $fromChild = $this->placeOrder($this->seller, $this->buyer, '100.00', $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value, 'category_id' => $child->id]));

    expect($fromParent->commission_rate)->toBe('8.00')
        ->and($general->commission_rate)->toBe('5.00')
        ->and($fromChild->commission_rate)->toBe('3.00');
});

it('keeps the frozen commission when the rate changes later', function (): void {
    $order = $this->placeOrder($this->seller, $this->buyer, '300.00');

    app(SettingsService::class)->put(PlatformSetting::COMMISSION_RATE, '9.00', $this->admin);

    $next = $this->placeOrder($this->seller, User::factory()->create(), '300.00');

    expect($order->fresh()->commission_amount)->toBe('15.00')
        ->and($order->fresh()->commission_rate)->toBe('5.00')
        ->and($next->commission_rate)->toBe('9.00')
        ->and($next->commission_amount)->toBe('27.00');
});

it('refuses the order, and the acceptance, when the seller is at the debt ceiling', function (): void {
    chargeDebt($this->seller, '100.00');
    $offer = $this->pendingOffer($this->seller, $this->buyer);

    $this->actingAs($this->seller, 'sanctum')
        ->postJson('/api/v1/offers/' . $offer->id . '/accept')
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::ORDER_SELLER_DEBT_CEILING->value);

    expect($offer->fresh()->status)->toBe(OfferStatus::PENDING)
        ->and(Order::query()->count())->toBe(0)
        ->and($offer->ad->fresh()->isReserved())->toBeFalse();
});

it('still takes orders just below the ceiling', function (): void {
    chargeDebt($this->seller, '99.99');

    expect($this->placeOrder($this->seller, $this->buyer)->status)->toBe(OrderStatus::CREATED);
});

it('lets only one of two racing acceptances on an ad place an order', function (): void {
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);
    $first = $this->pendingOffer($this->seller, $this->buyer, $ad);
    $second = $this->pendingOffer($this->seller, User::factory()->create(), $ad);

    // Both requests loaded a pending offer; the second one waits for the ad lock.
    $staleSecond = Offer::query()->findOrFail($second->id);
    $this->acceptOffer($first);

    try {
        app(AcceptOfferAction::class)($this->seller, $staleSecond);
        $this->fail('The second acceptance went through.');
    } catch (DomainException $exception) {
        expect($exception->errorCode)->toBe(ErrorCode::OFFER_NOT_PENDING);
    }

    expect(Order::query()->where('ad_id', $ad->id)->count())->toBe(1)
        ->and(Order::query()->where('ad_id', $ad->id)->value('source_id'))->toBe($first->id);
});

it('allows one open order per ad at the database level', function (): void {
    $order = $this->placeOrder($this->seller, $this->buyer);

    Order::factory()->create(['ad_id' => $order->ad_id, 'seller_id' => $this->seller->id]);
})->throws(UniqueConstraintViolationException::class);

it('refuses a second order on an ad that already has one open', function (): void {
    $order = $this->placeOrder($this->seller, $this->buyer);
    $ad = Ad::query()->findOrFail($order->ad_id);

    app(OrderPlacementService::class)->placeFromPurchaseRequest((string) Str::ulid(), User::factory()->create()->id, $ad, '50.00', 2);
})->throws(DomainException::class);

it('returns the same order when the same offer is placed again', function (): void {
    $offer = $this->pendingOffer($this->seller, $this->buyer);
    $order = $this->acceptOffer($offer);

    $again = app(OrderPlacementService::class)->placeFromAcceptedOffer($offer->fresh(), $offer->ad);

    expect($again->id)->toBe($order->id)
        ->and(Order::query()->count())->toBe(1);
});

it('prices a purchase request from the unit price and quantity', function (): void {
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value]);

    $order = app(OrderPlacementService::class)->placeFromPurchaseRequest((string) Str::ulid(), $this->buyer->id, $ad, '19.99', 3);

    expect($order->source)->toBe(OrderSource::PURCHASE_REQUEST)
        ->and($order->total)->toBe('59.97')
        ->and($order->commission_amount)->toBe('3.00');
});
