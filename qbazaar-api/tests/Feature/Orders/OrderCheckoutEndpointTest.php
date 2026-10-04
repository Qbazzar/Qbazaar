<?php

declare(strict_types=1);

use App\Enums\AdShipping;
use App\Enums\AdStatus;
use App\Enums\Fulfillment;
use App\Enums\OrderStatus;
use App\Events\Orders\OrderAwaitingHandover;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Order;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\UserAddress;
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
    $this->ad = $this->makeAd($this->seller, [
        'status' => AdStatus::ACTIVE->value,
        'price' => '200.00',
        'shipping' => AdShipping::DELIVERY->value,
        'shipping_fee' => '15.00',
        'quantity' => 4,
    ]);
    config(['qbazaar.commission.rate' => '5.00']);
});

function checkout(User $actor, Order $order, array $body, array $headers = []): TestResponse
{
    return test()->actingAs($actor, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/checkout', $body, $headers);
}

function orderFromPurchaseRequest(User $seller, User $buyer, Ad $ad, int $quantity = 1): Order
{
    test()->actingAs($buyer, 'sanctum')
        ->postJson('/api/v1/ads/' . $ad->id . '/purchase-requests', ['quantity' => $quantity])
        ->assertCreated();

    $request = PurchaseRequest::query()->where('buyer_id', $buyer->id)->sole();

    test()->actingAs($seller, 'sanctum')->postJson('/api/v1/purchase-requests/' . $request->id . '/accept')->assertOk();

    return Order::query()->where('source_id', $request->id)->sole();
}

it('shows the buyer the ways to receive the item with server-side totals', function (): void {
    $order = orderFromPurchaseRequest($this->seller, $this->buyer, $this->ad, 2);
    UserAddress::factory()->default()->create(['user_id' => $this->buyer->id]);

    $this->actingAs($this->buyer, 'sanctum')->getJson('/api/v1/orders/' . $order->id . '/checkout')
        ->assertOk()
        ->assertJsonPath('data.order.id', $order->id)
        ->assertJsonPath('data.fulfillment_options', ['pickup', 'delivery'])
        ->assertJsonPath('data.delivery_fee', '15.00')
        ->assertJsonPath('data.max_quantity', 4)
        ->assertJsonPath('data.payment_methods', ['cash'])
        ->assertJsonCount(1, 'data.saved_addresses')
        ->assertJsonPath('data.quotes.pickup.total', '400.00')
        ->assertJsonPath('data.quotes.delivery.shipping_fee', '15.00')
        ->assertJsonPath('data.quotes.delivery.total', '415.00')
        ->assertJsonMissingPath('data.quotes.delivery.commission');
});

it('lets only the buyer open or complete the checkout', function (): void {
    $order = orderFromPurchaseRequest($this->seller, $this->buyer, $this->ad);
    $stranger = User::factory()->create();

    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/orders/' . $order->id . '/checkout')->assertForbidden();
    checkout($this->seller, $order, ['fulfillment' => 'pickup', 'payment_method' => 'cash'])->assertForbidden();
    $this->actingAs($stranger, 'sanctum')->getJson('/api/v1/orders/' . $order->id . '/checkout')
        ->assertNotFound()
        ->assertJsonPath('error.code', ErrorCode::ORDER_NOT_FOUND->value);

    expect($order->fresh()->status)->toBe(OrderStatus::CREATED);
});

it('completes a pickup checkout without a shipping fee', function (): void {
    Event::fake([OrderAwaitingHandover::class]);
    $order = orderFromPurchaseRequest($this->seller, $this->buyer, $this->ad);

    checkout($this->buyer, $order, ['fulfillment' => 'pickup', 'payment_method' => 'cash'])
        ->assertOk()
        ->assertJsonPath('data.status', 'awaiting_handover')
        ->assertJsonPath('data.fulfillment', 'pickup')
        ->assertJsonPath('data.shipping_fee', '0.00')
        ->assertJsonPath('data.total', '200.00')
        ->assertJsonPath('data.delivery_address', null)
        ->assertJsonPath('data.payment_method', 'cash');

    Event::assertDispatched(OrderAwaitingHandover::class);
});

it('adds the seller\'s delivery fee and snapshots a saved address', function (): void {
    $order = orderFromPurchaseRequest($this->seller, $this->buyer, $this->ad);
    $address = UserAddress::factory()->create(['user_id' => $this->buyer->id, 'street' => 'Al Sadd Street']);

    checkout($this->buyer, $order, ['fulfillment' => 'delivery', 'address_id' => $address->id, 'payment_method' => 'cash'])
        ->assertOk()
        ->assertJsonPath('data.shipping_fee', '15.00')
        ->assertJsonPath('data.total', '215.00')
        ->assertJsonPath('data.delivery_address.street', 'Al Sadd Street');

    $address->update(['street' => 'Changed later']);
    $order->refresh();

    // The commission is on the item price only, never on shipping.
    expect($order->commission_amount)->toBe('10.00')
        ->and($order->fulfillment)->toBe(Fulfillment::DELIVERY)
        ->and($order->delivery_address['street'])->toBe('Al Sadd Street');
});

it('accepts an inline delivery address', function (): void {
    $order = orderFromPurchaseRequest($this->seller, $this->buyer, $this->ad);

    checkout($this->buyer, $order, [
        'fulfillment' => 'delivery',
        'payment_method' => 'cash',
        'address' => ['full_name' => 'Ahmed Ali', 'street' => 'Corniche', 'house_number' => '7', 'city' => 'Doha'],
    ])
        ->assertOk()
        ->assertJsonPath('data.delivery_address.full_name', 'Ahmed Ali')
        ->assertJsonPath('data.delivery_address.supplement', null);
});

it('refuses an address the buyer does not own', function (): void {
    $order = orderFromPurchaseRequest($this->seller, $this->buyer, $this->ad);
    $foreign = UserAddress::factory()->create();

    checkout($this->buyer, $order, ['fulfillment' => 'delivery', 'address_id' => $foreign->id, 'payment_method' => 'cash'])
        ->assertNotFound()
        ->assertJsonPath('error.code', ErrorCode::ADDRESS_NOT_FOUND->value);

    expect($order->fresh()->status)->toBe(OrderStatus::CREATED);
});

it('validates the checkout body', function (array $body, array $errors): void {
    $order = orderFromPurchaseRequest($this->seller, $this->buyer, $this->ad);

    checkout($this->buyer, $order, $body)
        ->assertStatus(422)
        ->assertJsonValidationErrors($errors, 'error.details');
})->with([
    'nothing' => [[], ['fulfillment', 'payment_method']],
    'unknown payment method' => [['fulfillment' => 'pickup', 'payment_method' => 'card'], ['payment_method']],
    'delivery without an address' => [['fulfillment' => 'delivery', 'payment_method' => 'cash'], ['address_id']],
    'delivery with an empty inline address' => [['fulfillment' => 'delivery', 'payment_method' => 'cash', 'address' => []], ['address_id']],
    'address on pickup' => [['fulfillment' => 'pickup', 'payment_method' => 'cash', 'address' => ['city' => 'Doha']], ['address']],
    'incomplete inline address' => [['fulfillment' => 'delivery', 'payment_method' => 'cash', 'address' => ['city' => 'Doha']], ['address.full_name', 'address.street']],
]);

it('refuses delivery when the ad is pickup only', function (): void {
    $this->ad->forceFill(['shipping' => AdShipping::PICKUP_ONLY])->save();
    $order = orderFromPurchaseRequest($this->seller, $this->buyer, $this->ad);

    checkout($this->buyer, $order, ['fulfillment' => 'delivery', 'payment_method' => 'cash', 'address' => [
        'full_name' => 'Ahmed Ali', 'street' => 'Corniche', 'house_number' => '7', 'city' => 'Doha',
    ]])
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::CHECKOUT_DELIVERY_UNAVAILABLE->value);
});

it('recomputes the totals and the commission when the buyer changes the quantity', function (): void {
    $order = orderFromPurchaseRequest($this->seller, $this->buyer, $this->ad);

    checkout($this->buyer, $order, ['fulfillment' => 'pickup', 'payment_method' => 'cash', 'quantity' => 5])
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::PURCHASE_QUANTITY_UNAVAILABLE->value)
        ->assertJsonPath('error.details.available', 4);

    checkout($this->buyer, $order, ['fulfillment' => 'pickup', 'payment_method' => 'cash', 'quantity' => 3])
        ->assertOk()
        ->assertJsonPath('data.quantity', 3)
        ->assertJsonPath('data.total', '600.00');

    expect($order->fresh()->commission_amount)->toBe('30.00');
});

it('keeps the agreed quantity of an offer deal', function (): void {
    $order = $this->placeOrder($this->seller, $this->buyer, '180.00', $this->ad);

    checkout($this->buyer, $order, ['fulfillment' => 'pickup', 'payment_method' => 'cash', 'quantity' => 2])
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::PURCHASE_QUANTITY_UNAVAILABLE->value);
});

it('returns the order unchanged when the checkout is retried', function (): void {
    $order = orderFromPurchaseRequest($this->seller, $this->buyer, $this->ad);

    checkout($this->buyer, $order, ['fulfillment' => 'pickup', 'payment_method' => 'cash'])->assertOk();
    checkout($this->buyer, $order, ['fulfillment' => 'pickup', 'payment_method' => 'cash', 'quantity' => 2])
        ->assertOk()
        ->assertJsonPath('data.status', 'awaiting_handover')
        ->assertJsonPath('data.quantity', 1);
});

it('refuses to check out a cancelled order', function (): void {
    $order = orderFromPurchaseRequest($this->seller, $this->buyer, $this->ad);
    $this->actingAs($this->buyer, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/cancel')->assertOk();

    checkout($this->buyer, $order, ['fulfillment' => 'pickup', 'payment_method' => 'cash'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::ORDER_INVALID_TRANSITION->value);
});
