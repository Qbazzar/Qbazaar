<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\ChatMessageKey;
use App\Enums\MessageType;
use App\Enums\OfferStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseRequestStatus;
use App\Events\PurchaseRequests\PurchaseRequestAccepted;
use App\Events\PurchaseRequests\PurchaseRequestCancelled;
use App\Events\PurchaseRequests\PurchaseRequestCreated;
use App\Events\PurchaseRequests\PurchaseRequestRejected;
use App\Events\PurchaseRequests\PurchaseRequestUpdated;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\Orders\OrderTransitionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesAds;
use Tests\Concerns\PlacesOrders;

uses(RefreshDatabase::class, CreatesAds::class, PlacesOrders::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->phoneVerified()->create();
    $this->buyer = User::factory()->phoneVerified()->create();
    $this->ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value, 'price' => '250.00', 'quantity' => 3]);
    config(['qbazaar.commission.rate' => '5.00']);
});

function requestToBuy(User $buyer, Ad $ad, array $body = []): TestResponse
{
    return test()->actingAs($buyer, 'sanctum')->postJson('/api/v1/ads/' . $ad->id . '/purchase-requests', $body);
}

function actOnPurchaseRequest(User $actor, PurchaseRequest $request, string $action): TestResponse
{
    return test()->actingAs($actor, 'sanctum')->postJson('/api/v1/purchase-requests/' . $request->id . '/' . $action);
}

function pendingPurchaseRequest(User $buyer, Ad $ad, int $quantity = 1): PurchaseRequest
{
    requestToBuy($buyer, $ad, ['quantity' => $quantity])->assertCreated();

    return PurchaseRequest::query()->where('buyer_id', $buyer->id)->where('ad_id', $ad->id)->latest('id')->firstOrFail();
}

it('creates a pending request at the ad price with a card in the conversation', function (): void {
    Event::fake([PurchaseRequestCreated::class]);

    requestToBuy($this->buyer, $this->ad, ['quantity' => 2, 'note' => 'Today please'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.unit_price', '250.00')
        ->assertJsonPath('data.quantity', 2)
        ->assertJsonPath('data.total', '500.00')
        ->assertJsonPath('data.viewer_role', 'buyer');

    $request = PurchaseRequest::query()->sole();
    $message = Message::query()->findOrFail($request->message_id);

    expect($request->conversation_id)->toBe(Conversation::query()->sole()->id)
        ->and($request->is_open)->toBeTrue()
        ->and($message->type)->toBe(MessageType::PURCHASE_REQUEST)
        ->and($message->message_key)->toBe(ChatMessageKey::PURCHASE_REQUEST_CREATED->value)
        ->and($message->params)->toMatchArray(['amount' => '500.00', 'currency' => 'QAR', 'quantity' => 2, 'note' => 'Today please']);

    Event::assertDispatched(PurchaseRequestCreated::class, fn (PurchaseRequestCreated $event): bool => $event->otherUserId === $this->seller->id
        && $event->broadcastAs() === 'purchase_request.created');
});

it('shows the card with its request in the conversation transcript', function (): void {
    $request = pendingPurchaseRequest($this->buyer, $this->ad);

    $this->actingAs($this->seller, 'sanctum')
        ->getJson('/api/v1/conversations/' . $request->conversation_id . '/messages')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'purchase_request')
        ->assertJsonPath('data.0.purchase_request.id', $request->id)
        ->assertJsonPath('data.0.purchase_request.viewer_role', 'seller');
});

it('allows one pending request per buyer and ad', function (): void {
    pendingPurchaseRequest($this->buyer, $this->ad);

    requestToBuy($this->buyer, $this->ad)
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::PURCHASE_REQUEST_OPEN_EXISTS->value);
});

it('backs the one open request rule with the database, whatever the race', function (): void {
    $request = pendingPurchaseRequest($this->buyer, $this->ad);
    $duplicate = fn () => PurchaseRequest::factory()->create([
        'conversation_id' => $request->conversation_id,
        'ad_id' => $this->ad->id,
        'buyer_id' => $this->buyer->id,
        'seller_id' => $this->seller->id,
    ]);

    expect($duplicate)->toThrow(UniqueConstraintViolationException::class);

    actOnPurchaseRequest($this->buyer, $request, 'cancel')->assertOk();

    expect($duplicate()->is_open)->toBeTrue();
});

it('refuses a request the ad cannot satisfy', function (array $adOverrides, array $body, ErrorCode $code): void {
    $ad = $this->makeAd($this->seller, ['status' => AdStatus::ACTIVE->value, 'price' => '100.00', ...$adOverrides]);

    requestToBuy($this->buyer, $ad, $body)
        ->assertStatus(422)
        ->assertJsonPath('error.code', $code->value);

    expect(PurchaseRequest::query()->count())->toBe(0);
})->with([
    'no price' => [['price_type' => 'contact', 'price' => null], [], ErrorCode::PURCHASE_REQUEST_AD_NOT_AVAILABLE],
    'wanted ad' => [['ad_type' => 'wanted'], [], ErrorCode::PURCHASE_REQUEST_AD_NOT_AVAILABLE],
    'too many units' => [['quantity' => 2], ['quantity' => 3], ErrorCode::PURCHASE_QUANTITY_UNAVAILABLE],
]);

it('refuses a request on the buyer\'s own ad', function (): void {
    requestToBuy($this->seller, $this->ad)
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::PURCHASE_REQUEST_OWN_AD->value);
});

it('validates the quantity and the note', function (): void {
    requestToBuy($this->buyer, $this->ad, ['quantity' => 0, 'note' => '<b>hi</b>'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['quantity', 'note'], 'error.details');
});

it('lets the buyer edit a pending request at the current ad price', function (): void {
    Event::fake([PurchaseRequestUpdated::class]);
    $request = pendingPurchaseRequest($this->buyer, $this->ad);
    $this->ad->forceFill(['price' => '240.00'])->save();

    $this->actingAs($this->buyer, 'sanctum')
        ->putJson('/api/v1/purchase-requests/' . $request->id, ['quantity' => 3])
        ->assertOk()
        ->assertJsonPath('data.unit_price', '240.00')
        ->assertJsonPath('data.total', '720.00');

    expect(Message::query()->where('message_key', ChatMessageKey::PURCHASE_REQUEST_UPDATED->value)->exists())->toBeTrue();
    Event::assertDispatched(PurchaseRequestUpdated::class);

    $this->actingAs($this->seller, 'sanctum')
        ->putJson('/api/v1/purchase-requests/' . $request->id, ['quantity' => 1])
        ->assertForbidden();
});

it('places the order when the seller accepts and closes every competing deal on the ad', function (): void {
    Event::fake([PurchaseRequestAccepted::class, PurchaseRequestCancelled::class]);
    $request = pendingPurchaseRequest($this->buyer, $this->ad, 2);
    $otherBuyer = User::factory()->phoneVerified()->create();
    $competing = pendingPurchaseRequest($otherBuyer, $this->ad);
    $offer = $this->pendingOffer($this->seller, User::factory()->create(), $this->ad);

    actOnPurchaseRequest($this->seller, $request, 'accept')
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    $request->refresh();
    $order = Order::query()->sole();

    expect($order->source)->toBe(OrderSource::PURCHASE_REQUEST)
        ->and($order->source_id)->toBe($request->id)
        ->and($order->buyer_id)->toBe($this->buyer->id)
        ->and($order->unit_price)->toBe('250.00')
        ->and($order->quantity)->toBe(2)
        ->and($order->total)->toBe('500.00')
        ->and($order->commission_amount)->toBe('25.00')
        ->and($request->order_id)->toBe($order->id)
        ->and($request->is_open)->toBeNull()
        ->and($this->ad->fresh()->isReserved())->toBeTrue()
        ->and($competing->fresh()->status)->toBe(PurchaseRequestStatus::CANCELLED)
        ->and($competing->fresh()->cancelled_by)->toBeNull()
        ->and($offer->fresh()->status)->toBe(OfferStatus::EXPIRED);

    Event::assertDispatched(PurchaseRequestAccepted::class, fn (PurchaseRequestAccepted $event): bool => $event->otherUserId === $this->buyer->id);
    Event::assertDispatched(PurchaseRequestCancelled::class, fn (PurchaseRequestCancelled $event): bool => $event->otherUserId === $otherBuyer->id);
});

it('lets only the seller accept or reject', function (): void {
    $request = pendingPurchaseRequest($this->buyer, $this->ad);

    actOnPurchaseRequest($this->buyer, $request, 'accept')->assertForbidden();
    actOnPurchaseRequest($this->buyer, $request, 'reject')->assertForbidden();
    actOnPurchaseRequest($this->seller, $request, 'cancel')->assertForbidden();

    expect($request->fresh()->status)->toBe(PurchaseRequestStatus::PENDING);
});

it('cancels pending requests when an offer on the ad is accepted', function (): void {
    $request = pendingPurchaseRequest($this->buyer, $this->ad);

    $this->placeOrder($this->seller, User::factory()->create(), '200.00', $this->ad);

    expect($request->fresh()->status)->toBe(PurchaseRequestStatus::CANCELLED);

    actOnPurchaseRequest($this->seller, $request, 'accept')
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::PURCHASE_REQUEST_NOT_PENDING->value);

    expect(Order::query()->count())->toBe(1);
});

it('changes nothing when the ad went back to review before the seller accepts', function (): void {
    $request = pendingPurchaseRequest($this->buyer, $this->ad);
    $this->ad->forceFill(['status' => AdStatus::PENDING])->save();

    actOnPurchaseRequest($this->seller, $request, 'accept')
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::PURCHASE_REQUEST_AD_NOT_AVAILABLE->value);

    expect($request->fresh()->status)->toBe(PurchaseRequestStatus::PENDING)
        ->and(Order::query()->count())->toBe(0);
});

it('lets the seller reject and the buyer cancel a pending request', function (): void {
    Event::fake([PurchaseRequestRejected::class, PurchaseRequestCancelled::class]);
    $rejected = pendingPurchaseRequest($this->buyer, $this->ad);

    actOnPurchaseRequest($this->seller, $rejected, 'reject')->assertOk()->assertJsonPath('data.status', 'rejected');

    $cancelled = pendingPurchaseRequest($this->buyer, $this->ad);

    actOnPurchaseRequest($this->buyer, $cancelled, 'cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancelled_by', $this->buyer->id);

    actOnPurchaseRequest($this->buyer, $cancelled, 'cancel')
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::PURCHASE_REQUEST_NOT_PENDING->value);

    Event::assertDispatched(PurchaseRequestRejected::class);
    Event::assertDispatched(PurchaseRequestCancelled::class, fn (PurchaseRequestCancelled $event): bool => $event->otherUserId === $this->seller->id);
});

it('hides a request from everyone but its buyer and seller', function (): void {
    $request = pendingPurchaseRequest($this->buyer, $this->ad);
    $stranger = User::factory()->phoneVerified()->create();

    actOnPurchaseRequest($stranger, $request, 'accept')
        ->assertNotFound()
        ->assertJsonPath('error.code', ErrorCode::PURCHASE_REQUEST_NOT_FOUND->value);
    actOnPurchaseRequest($stranger, $request, 'cancel')->assertNotFound();
});

it('cancels pending requests when the ad leaves the market', function (): void {
    $request = pendingPurchaseRequest($this->buyer, $this->ad);

    $this->ad->delete();

    expect($request->fresh()->status)->toBe(PurchaseRequestStatus::CANCELLED);
});

it('marks the request paid once its order is handed over', function (): void {
    $request = pendingPurchaseRequest($this->buyer, $this->ad);
    actOnPurchaseRequest($this->seller, $request, 'accept')->assertOk();
    $order = Order::query()->sole();

    app(OrderTransitionService::class)->awaitHandover($order, PaymentMethod::CASH);
    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/confirm-handover')->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::COMPLETED)
        ->and($request->fresh()->status)->toBe(PurchaseRequestStatus::PAID)
        ->and($request->fresh()->paid_at)->not->toBeNull()
        ->and(Message::query()->where('message_key', ChatMessageKey::PURCHASE_REQUEST_PAID->value)->exists())->toBeTrue();
});
