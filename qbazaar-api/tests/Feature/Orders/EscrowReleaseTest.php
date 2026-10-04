<?php

declare(strict_types=1);

use App\Data\Ledger\LedgerActor;
use App\Enums\LedgerAccountType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ErrorCode;
use App\Jobs\Orders\ReleaseDueEscrowOrdersJob;
use App\Jobs\Orders\ReleaseEscrowOrdersBatchJob;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\User;
use App\Services\Ledger\LedgerRecipes;
use App\Services\Orders\OrderDisputeWindow;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGateways;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesAds;
use Tests\Concerns\ManagesMoney;
use Tests\Concerns\PlacesOrders;

uses(RefreshDatabase::class, CreatesAds::class, PlacesOrders::class, ManagesMoney::class);

/**
 * Stands in for the M7 gateway: the buyer's money is held in escrow and the
 * handover releases it through the real recipes.
 */
function escrowGateway(): PaymentGateway
{
    return new class(app(LedgerRecipes::class)) implements PaymentGateway
    {
        public function __construct(private readonly LedgerRecipes $recipes) {}

        public function method(): PaymentMethod
        {
            return PaymentMethod::CASH;
        }

        public function holdsFundsInEscrow(): bool
        {
            return true;
        }

        public function settleHandover(Order $order, LedgerActor $actor): ?LedgerTransaction
        {
            return $this->recipes->releaseEscrow($order, $actor);
        }

        public function settleCancellation(Order $order, LedgerActor $actor): ?LedgerTransaction
        {
            return $this->recipes->refundOrderPayment($order, $actor);
        }
    };
}

beforeEach(function (): void {
    $this->seedReferenceData();
    Notification::fake();
    config(['qbazaar.commission.rate' => '5.00']);

    $this->seller = User::factory()->create();
    $this->buyer = User::factory()->create();
});

function useEscrowGateway(): void
{
    app()->instance(PaymentGateways::class, new PaymentGateways(escrowGateway()));
}

function paidEscrowOrder(Order $order): Order
{
    app(LedgerRecipes::class)->receiveOrderPayment($order, LedgerAccountType::PLATFORM_BANK, LedgerActor::system());

    return $order;
}

it('keeps an escrow order open after the seller\'s handover, until the buyer confirms receipt', function (): void {
    useEscrowGateway();
    $order = paidEscrowOrder($this->orderAwaitingHandover($this->seller, $this->buyer, '200.00'));

    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/confirm-handover')
        ->assertOk()
        ->assertJsonPath('data.status', 'awaiting_handover');

    expect($order->refresh()->handed_over_at)->not->toBeNull()
        ->and($this->balanceOf(LedgerAccountType::PLATFORM_ESCROW))->toBe('200.00');

    $this->actingAs($this->buyer, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/confirm-receipt')
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect($this->balanceOf(LedgerAccountType::PLATFORM_ESCROW))->toBe('0.00')
        ->and($this->balanceOf(LedgerAccountType::USER_WALLET, $this->seller))->toBe('190.00')
        ->and($this->balanceOf(LedgerAccountType::PLATFORM_REVENUE))->toBe('10.00');
});

it('lets the buyer of an escrow order report a problem until the release, not after', function (): void {
    useEscrowGateway();
    $order = paidEscrowOrder($this->orderAwaitingHandover($this->seller, $this->buyer));
    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/confirm-handover')->assertOk();

    $this->travel(2)->days();
    $this->actingAs($this->buyer, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/report-problem', ['reason' => 'Arrived broken in the box.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'disputed');

    $second = paidEscrowOrder($this->orderAwaitingHandover($this->seller, $this->buyer));
    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/orders/' . $second->id . '/confirm-handover')->assertOk();
    $this->travel(3)->days();
    $this->travel(1)->seconds();
    $this->actingAs($this->buyer, 'sanctum')->postJson('/api/v1/orders/' . $second->id . '/report-problem', ['reason' => 'Arrived broken in the box.'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', ErrorCode::DISPUTE_WINDOW_CLOSED->value);
});

it('releases escrow orders handed over more than the admin-set days ago, skipping disputed ones', function (): void {
    useEscrowGateway();
    $due = paidEscrowOrder($this->orderAwaitingHandover($this->seller, $this->buyer, '200.00'));
    $disputed = paidEscrowOrder($this->orderAwaitingHandover($this->seller, $this->buyer, '100.00'));
    $notHandedOver = paidEscrowOrder($this->orderAwaitingHandover($this->seller, $this->buyer, '50.00'));

    foreach ([$due, $disputed] as $order) {
        $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/confirm-handover')->assertOk();
    }
    $this->actingAs($this->buyer, 'sanctum')->postJson('/api/v1/orders/' . $disputed->id . '/report-problem', ['reason' => 'Arrived broken in the box.'])->assertOk();

    $this->travel(3)->days();
    $this->travel(1)->minutes();
    (new ReleaseDueEscrowOrdersJob)->handle(app(OrderDisputeWindow::class));
    (new ReleaseDueEscrowOrdersJob)->handle(app(OrderDisputeWindow::class));

    expect($due->refresh()->status)->toBe(OrderStatus::COMPLETED)
        ->and($disputed->refresh()->status)->toBe(OrderStatus::DISPUTED)
        ->and($notHandedOver->refresh()->status)->toBe(OrderStatus::AWAITING_HANDOVER)
        ->and(LedgerTransaction::query()->where('idempotency_key', "order:{$due->id}:outcome")->count())->toBe(1)
        ->and($this->balanceOf(LedgerAccountType::USER_WALLET, $this->seller))->toBe('190.00')
        ->and($this->balanceOf(LedgerAccountType::PLATFORM_ESCROW))->toBe('150.00');
});

it('waits for the configured number of days', function (): void {
    useEscrowGateway();
    $order = paidEscrowOrder($this->orderAwaitingHandover($this->seller, $this->buyer));
    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/confirm-handover')->assertOk();

    $this->travel(2)->days();
    dispatch_sync(new ReleaseDueEscrowOrdersJob);

    expect($order->refresh()->status)->toBe(OrderStatus::AWAITING_HANDOVER);
});

it('queues batches on the low queue and never overlaps runs', function (): void {
    useEscrowGateway();
    Queue::fake();
    $order = paidEscrowOrder($this->orderAwaitingHandover($this->seller, $this->buyer));
    $this->actingAs($this->seller, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/confirm-handover')->assertOk();
    $this->travel(4)->days();

    (new ReleaseDueEscrowOrdersJob)->handle(app(OrderDisputeWindow::class));

    expect(new ReleaseDueEscrowOrdersJob)->toBeInstanceOf(ShouldBeUnique::class)
        ->and((new ReleaseDueEscrowOrdersJob)->queue)->toBe('low');
    Queue::assertPushedOn('low', ReleaseEscrowOrdersBatchJob::class, fn (ReleaseEscrowOrdersBatchJob $job): bool => $job->orderIds === [$order->id]);
});

it('leaves cash orders alone: the seller\'s confirmation already completed them', function (): void {
    $order = $this->orderAwaitingHandover($this->seller, $this->buyer);
    $this->travel(10)->days();

    dispatch_sync(new ReleaseDueEscrowOrdersJob);

    expect($order->refresh()->status)->toBe(OrderStatus::AWAITING_HANDOVER)
        ->and($this->actingAs($this->buyer, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/confirm-receipt')->assertForbidden())->not->toBeNull();
});
