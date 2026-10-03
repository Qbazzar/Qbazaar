<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerTransactionType;
use App\Enums\OrderStatus;
use App\Events\Orders\OrderCancelled;
use App\Events\Orders\OrderCompleted;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\User;
use App\Services\Ledger\LedgerAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesAds;
use Tests\Concerns\PlacesOrders;

uses(RefreshDatabase::class, CreatesAds::class, PlacesOrders::class);

beforeEach(function (): void {
    $this->seedReferenceData();
    $this->seller = User::factory()->create();
    $this->buyer = User::factory()->create();
    config(['qbazaar.commission.rate' => '5.00']);
});

function confirmHandover(User $actor, Order $order, ?string $idempotencyKey = null)
{
    return test()->actingAs($actor, 'sanctum')->postJson(
        '/api/v1/orders/' . $order->id . '/confirm-handover',
        [],
        $idempotencyKey === null ? [] : ['X-Idempotency-Key' => $idempotencyKey],
    );
}

it('completes a cash order on the seller\'s confirmation and books the commission in the same step', function (): void {
    Event::fake([OrderCompleted::class]);
    $order = $this->orderAwaitingHandover($this->seller, $this->buyer, '400.00');

    confirmHandover($this->seller, $order)
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.commission.amount', '20.00');

    $order->refresh();
    $transaction = LedgerTransaction::query()->sole();

    expect($order->status)->toBe(OrderStatus::COMPLETED)
        ->and($order->completed_at)->not->toBeNull()
        ->and($order->active_ad_id)->toBeNull()
        ->and($transaction->type)->toBe(LedgerTransactionType::COMMISSION_CHARGED)
        ->and($transaction->reference_id)->toBe($order->id)
        ->and(app(LedgerAccounts::class)->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $this->seller->id))->toBe('20.00')
        ->and(app(LedgerAccounts::class)->balanceOf(LedgerAccountType::PLATFORM_REVENUE))->toBe('20.00')
        ->and(Ad::query()->find($order->ad_id)->status)->toBe(AdStatus::SOLD);

    Event::assertDispatched(OrderCompleted::class);
});

it('posts the commission once when the handover is confirmed twice', function (): void {
    $order = $this->orderAwaitingHandover($this->seller, $this->buyer, '400.00');
    $staleCopy = Order::query()->findOrFail($order->id);

    confirmHandover($this->seller, $order)->assertOk();
    confirmHandover($this->seller, $staleCopy)->assertOk()->assertJsonPath('data.status', 'completed');

    expect(LedgerTransaction::query()->count())->toBe(1)
        ->and(app(LedgerAccounts::class)->balanceOf(LedgerAccountType::USER_COMMISSION_RECEIVABLE, $this->seller->id))->toBe('20.00');
});

it('replays a retried confirmation with the same idempotency key', function (): void {
    $order = $this->orderAwaitingHandover($this->seller, $this->buyer);
    $key = '01HZY7Q4C9V3N8B2K6M5T1R0WX';

    confirmHandover($this->seller, $order, $key)->assertOk();
    confirmHandover($this->seller, $order, $key)->assertOk()->assertHeader('X-Idempotent-Replay', 'true');

    expect(LedgerTransaction::query()->count())->toBe(1);
});

it('refuses to confirm a handover before checkout', function (): void {
    $order = $this->placeOrder($this->seller, $this->buyer);

    confirmHandover($this->seller, $order)
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::ORDER_INVALID_TRANSITION->value);

    expect(LedgerTransaction::query()->count())->toBe(0);
});

it('lets only the seller confirm the handover', function (): void {
    $order = $this->orderAwaitingHandover($this->seller, $this->buyer);

    confirmHandover($this->buyer, $order)->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::AWAITING_HANDOVER);
});

it('hides an order from everyone but its two participants', function (): void {
    $order = $this->placeOrder($this->seller, $this->buyer);
    $stranger = User::factory()->create();

    $this->actingAs($stranger, 'sanctum')->getJson('/api/v1/orders/' . $order->id)
        ->assertNotFound()
        ->assertJsonPath('error.code', ErrorCode::ORDER_NOT_FOUND->value);
    confirmHandover($stranger, $order)->assertNotFound();
    $this->actingAs($stranger, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/cancel')->assertNotFound();
    $this->actingAs($stranger, 'sanctum')->getJson('/api/v1/orders/01HZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
});

it('shows the commission to the seller only', function (): void {
    $order = $this->placeOrder($this->seller, $this->buyer, '200.00');

    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/orders/' . $order->id)
        ->assertOk()
        ->assertJsonPath('data.viewer_role', 'seller')
        ->assertJsonPath('data.commission.rate', '5.00')
        ->assertJsonPath('data.commission.amount', '10.00')
        ->assertJsonPath('data.total', '200.00')
        ->assertJsonPath('data.currency', 'QAR');

    $this->actingAs($this->buyer, 'sanctum')->getJson('/api/v1/orders/' . $order->id)
        ->assertOk()
        ->assertJsonPath('data.viewer_role', 'buyer')
        ->assertJsonPath('data.commission', null);
});

it('requires authentication', function (): void {
    $order = Order::factory()->create(['ad_id' => null]);

    $this->getJson('/api/v1/orders/' . $order->id)->assertUnauthorized();
    $this->getJson('/api/v1/account/orders')->assertUnauthorized();
});

it('cancels before the handover, releases the ad and lets it sell again', function (): void {
    Event::fake([OrderCancelled::class]);
    $order = $this->placeOrder($this->seller, $this->buyer);

    $this->actingAs($this->buyer, 'sanctum')
        ->postJson('/api/v1/orders/' . $order->id . '/cancel', ['reason' => 'Found one closer to home'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancellation_reason', 'Found one closer to home');

    $order->refresh();
    $ad = Ad::query()->findOrFail($order->ad_id);

    expect($order->cancelled_by)->toBe($this->buyer->id)
        ->and($order->active_ad_id)->toBeNull()
        ->and($ad->isReserved())->toBeFalse()
        ->and(LedgerTransaction::query()->count())->toBe(0);

    Event::assertDispatched(OrderCancelled::class);

    $next = $this->placeOrder($this->seller, User::factory()->create(), '150.00', $ad);
    expect($next->status)->toBe(OrderStatus::CREATED)
        ->and($ad->fresh()->isReserved())->toBeTrue();
});

it('lets the seller cancel too, and treats a repeated cancel as done', function (): void {
    $order = $this->orderAwaitingHandover($this->seller, $this->buyer);

    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/cancel')->assertOk();
    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
});

it('refuses to cancel a completed order', function (): void {
    $order = $this->orderAwaitingHandover($this->seller, $this->buyer);
    confirmHandover($this->seller, $order)->assertOk();

    $this->actingAs($this->buyer, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/cancel')
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::ORDER_INVALID_TRANSITION->value);
});

it('validates the cancellation reason', function (): void {
    $order = $this->placeOrder($this->seller, $this->buyer);

    $this->actingAs($this->buyer, 'sanctum')
        ->postJson('/api/v1/orders/' . $order->id . '/cancel', ['reason' => str_repeat('a', 501)])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('lists purchases and sales separately, newest first, with a status filter and a cursor', function (): void {
    config(['qbazaar.orders.per_page' => 2]);
    $purchases = collect(range(1, 3))->map(function (int $i): Order {
        $this->travel($i)->minutes();

        return $this->placeOrder($this->seller, $this->buyer, "{$i}0.00");
    });
    $cancelled = $purchases->first();
    $this->actingAs($this->buyer, 'sanctum')->postJson('/api/v1/orders/' . $cancelled->id . '/cancel')->assertOk();
    $this->placeOrder(User::factory()->create(), $this->seller);

    $firstPage = $this->actingAs($this->buyer, 'sanctum')->getJson('/api/v1/account/orders')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $purchases[2]->id)
        ->assertJsonPath('meta.has_more', true);

    $this->actingAs($this->buyer, 'sanctum')->getJson('/api/v1/account/orders?cursor=' . $firstPage->json('meta.next_cursor'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $cancelled->id);

    $this->actingAs($this->buyer, 'sanctum')->getJson('/api/v1/account/orders?status=cancelled')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/account/orders?role=seller')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($this->seller, 'sanctum')->getJson('/api/v1/account/orders?role=buyer')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->buyer, 'sanctum')->getJson('/api/v1/account/orders?role=admin&status=paid')
        ->assertStatus(422);
});
