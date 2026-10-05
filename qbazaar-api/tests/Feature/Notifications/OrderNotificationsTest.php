<?php

declare(strict_types=1);

use App\Data\Account\NotificationPreferences;
use App\Enums\Language;
use App\Enums\OfferParty;
use App\Enums\OrderNotice;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Events\Orders\OrderAwaitingHandover;
use App\Events\Orders\OrderCancelled;
use App\Events\Orders\OrderCompleted;
use App\Events\Orders\OrderCreated;
use App\Events\Orders\OrderDisputed;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Orders\OrderActivityNotification;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * Who hears about each order event, and what they receive.
 */
beforeEach(function (): void {
    Notification::fake();
});

/**
 * @param array<string, mixed> $attributes
 */
function notifiedOrder(OrderStatus $status, array $attributes = []): Order
{
    return Order::factory()->status($status)->create(['ad_id' => null, ...$attributes]);
}

/**
 * @return list<string> the notices the user received, as values
 */
function orderNoticesFor(?User $user): array
{
    $notices = [];

    foreach (Notification::sent($user, OrderActivityNotification::class) as $notification) {
        $notices[] = $notification->notice->value;
    }

    sort($notices);

    return $notices;
}

it('tells the proposer of the accepted offer that the order was placed', function (OfferParty $proposer): void {
    $this->seed([CategorySeeder::class, LocationSeeder::class]);
    $order = notifiedOrder(OrderStatus::CREATED);
    $offer = Offer::factory()->accepted()->create(['proposed_by' => $proposer, 'buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id]);
    $order->forceFill(['source' => OrderSource::OFFER, 'source_id' => $offer->id])->save();

    OrderCreated::dispatch($order);

    $told = $proposer === OfferParty::BUYER ? $order->buyer : $order->seller;
    $actor = $proposer === OfferParty::BUYER ? $order->seller : $order->buyer;

    expect(orderNoticesFor($told))->toBe([OrderNotice::CREATED->value])
        ->and(orderNoticesFor($actor))->toBe([]);
})->with([OfferParty::BUYER, OfferParty::SELLER]);

it('tells the buyer when the seller accepts their purchase request', function (): void {
    $order = notifiedOrder(OrderStatus::CREATED, ['source' => OrderSource::PURCHASE_REQUEST]);

    OrderCreated::dispatch($order);

    expect(orderNoticesFor($order->buyer))->toBe([OrderNotice::CREATED->value])
        ->and(orderNoticesFor($order->seller))->toBe([]);
});

it('tells only the seller that the checkout is done', function (): void {
    $order = notifiedOrder(OrderStatus::AWAITING_HANDOVER);

    OrderAwaitingHandover::dispatch($order);

    expect(orderNoticesFor($order->seller))->toBe([OrderNotice::AWAITING_HANDOVER->value])
        ->and(orderNoticesFor($order->buyer))->toBe([]);
});

it('tells the buyer about the handover and the seller about the commission', function (): void {
    $order = notifiedOrder(OrderStatus::COMPLETED);

    OrderCompleted::dispatch($order);

    expect(orderNoticesFor($order->buyer))->toBe([OrderNotice::HANDED_OVER->value])
        ->and(orderNoticesFor($order->seller))->toBe([OrderNotice::COMMISSION_DUE->value]);
});

it('sends no commission notice when no commission was charged', function (): void {
    $order = notifiedOrder(OrderStatus::COMPLETED, ['commission_rate' => '0.00', 'commission_amount' => '0.00']);

    OrderCompleted::dispatch($order);

    expect(orderNoticesFor($order->seller))->toBe([]);
});

it('tells the other side of a cancellation, or both when nobody of them cancelled', function (): void {
    $byBuyer = notifiedOrder(OrderStatus::CANCELLED);
    $byBuyer->forceFill(['cancelled_by' => $byBuyer->buyer_id])->save();
    $bySystem = notifiedOrder(OrderStatus::CANCELLED);

    OrderCancelled::dispatch($byBuyer);
    OrderCancelled::dispatch($bySystem);

    expect(orderNoticesFor($byBuyer->seller))->toBe([OrderNotice::CANCELLED->value])
        ->and(orderNoticesFor($byBuyer->buyer))->toBe([])
        ->and(orderNoticesFor($bySystem->buyer))->toBe([OrderNotice::CANCELLED->value])
        ->and(orderNoticesFor($bySystem->seller))->toBe([OrderNotice::CANCELLED->value]);
});

it('tells the seller that the buyer reported a problem', function (): void {
    $order = notifiedOrder(OrderStatus::DISPUTED);

    OrderDisputed::dispatch($order);

    expect(orderNoticesFor($order->seller))->toBe([OrderNotice::DISPUTE_OPENED->value])
        ->and(orderNoticesFor($order->buyer))->toBe([]);
});

it('tells both sides about an admin ruling on a dispute, without repeating the commission notice', function (string $outcome): void {
    $order = notifiedOrder($outcome === 'completed' ? OrderStatus::COMPLETED : OrderStatus::CANCELLED, ['disputed_at' => now()]);

    $outcome === 'completed' ? OrderCompleted::dispatch($order) : OrderCancelled::dispatch($order);

    expect(orderNoticesFor($order->buyer))->toBe([OrderNotice::DISPUTE_RESOLVED->value])
        ->and(orderNoticesFor($order->seller))->toBe([OrderNotice::DISPUTE_RESOLVED->value]);
})->with(['completed', 'cancelled']);

it('loads both participants with one query whatever the notices', function (): void {
    $order = notifiedOrder(OrderStatus::COMPLETED, ['disputed_at' => now()]);

    DB::enableQueryLog();
    OrderCompleted::dispatch($order);

    $userQueries = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'from "users"'));

    expect($userQueries)->toHaveCount(1);
});

it('delivers on the notifications queue and leaves out the channels the user muted', function (): void {
    $buyer = User::factory()->create([
        'notification_preferences' => NotificationPreferences::defaults()->with(email: ['offers' => false]),
    ]);
    $order = notifiedOrder(OrderStatus::COMPLETED, ['buyer_id' => $buyer->id]);
    $notification = new OrderActivityNotification(OrderNotice::HANDED_OVER, $order);

    expect($notification->via($buyer))->toBe(['database'])
        ->and($notification->via($order->seller))->toBe(['mail', 'database'])
        ->and($notification->viaQueues()['database'])->toBe(QueueName::NOTIFICATIONS->value);
});

it('writes the inbox row in the recipient language', function (): void {
    Notification::swap(new ChannelManager(app()));
    $buyer = User::factory()->create(['language' => Language::ENGLISH]);
    $order = notifiedOrder(OrderStatus::COMPLETED, ['buyer_id' => $buyer->id, 'ad_title' => 'Blue bike']);

    $buyer->notifyNow(new OrderActivityNotification(OrderNotice::HANDED_OVER, $order), ['database']);

    $data = json_decode((string) DB::table('notifications')->where('notifiable_id', $buyer->id)->value('data'), true);

    expect($data)->toMatchArray([
        'category' => 'order.handed_over',
        'title' => 'Order completed',
        'order_id' => $order->id,
    ])->and($data['body'])->toContain('Blue bike')
        ->and($data['cta_url'])->toEndWith('/account/orders/' . $order->id);
});
