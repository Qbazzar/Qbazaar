<?php

declare(strict_types=1);

use App\Data\Ledger\LedgerActor;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\Orders\OrderAwaitingHandover;
use App\Events\Orders\OrderDisputed;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Services\Orders\OrderTransitionService;
use App\Services\Payments\CashGateway;
use App\Services\Payments\PaymentGateways;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Every move between order statuses through the service, allowed and
 * refused. The HTTP flows are covered in OrderEndpointTest.
 */
beforeEach(function (): void {
    $this->transitions = app(OrderTransitionService::class);
});

function orderIn(OrderStatus $status): Order
{
    return Order::factory()->status($status)->create(['ad_id' => null]);
}

function attemptOrderMove(Closure $move): ?ErrorCode
{
    try {
        $move();

        return null;
    } catch (DomainException $exception) {
        return $exception->errorCode;
    }
}

dataset('moves', [
    'checkout' => ['awaitHandover', OrderStatus::AWAITING_HANDOVER],
    'complete' => ['complete', OrderStatus::COMPLETED],
    'resolve as completed' => ['resolveCompleted', OrderStatus::COMPLETED],
    'cancel' => ['cancel', OrderStatus::CANCELLED],
    'resolve as cancelled' => ['resolveCancelled', OrderStatus::CANCELLED],
    'dispute' => ['dispute', OrderStatus::DISPUTED],
]);

function moveOrder(OrderTransitionService $transitions, string $move, Order $order): Order
{
    $system = LedgerActor::system();

    return match ($move) {
        'awaitHandover' => $transitions->awaitHandover($order, PaymentMethod::CASH),
        'complete' => $transitions->complete($order, $system),
        'resolveCompleted' => $transitions->complete($order, $system, resolvingDispute: true),
        'cancel' => $transitions->cancel($order, $system, null, null),
        'resolveCancelled' => $transitions->cancel($order, $system, null, 'Admin ruling', resolvingDispute: true),
        'dispute' => $transitions->dispute($order),
    };
}

it('allows exactly the transitions the state machine declares', function (string $move, OrderStatus $target): void {
    foreach (OrderStatus::cases() as $from) {
        $order = orderIn($from);
        $error = attemptOrderMove(fn () => moveOrder($this->transitions, $move, $order));
        $order->refresh();

        $sameStatusNoOp = $from === $target && in_array($target, [OrderStatus::COMPLETED, OrderStatus::CANCELLED], true);
        $userMoveOnDispute = $from === OrderStatus::DISPUTED && in_array($move, ['complete', 'cancel'], true);
        $allowed = $sameStatusNoOp || ($from->canTransitionTo($target) && ! $userMoveOnDispute);

        if ($allowed) {
            expect($error)->toBeNull("{$move} from {$from->value}")
                ->and($order->status)->toBe($target);
        } else {
            expect($error)->toBe(ErrorCode::ORDER_INVALID_TRANSITION, "{$move} from {$from->value}")
                ->and($order->status)->toBe($from);
        }
    }
})->with('moves');

it('stamps the time of every status it enters', function (): void {
    $order = orderIn(OrderStatus::CREATED);

    $this->transitions->awaitHandover($order, PaymentMethod::CASH);
    $this->transitions->dispute($order);
    $this->transitions->cancel($order, LedgerActor::system(), null, 'Seller never showed up', resolvingDispute: true);

    $order->refresh();

    expect($order->awaiting_handover_at)->not->toBeNull()
        ->and($order->disputed_at)->not->toBeNull()
        ->and($order->cancelled_at)->not->toBeNull()
        ->and($order->completed_at)->toBeNull()
        ->and($order->cancellation_reason)->toBe('Seller never showed up');
});

it('keeps a disputed order holding its ad', function (): void {
    $order = orderIn(OrderStatus::AWAITING_HANDOVER);

    $this->transitions->dispute($order);

    expect($order->fresh()->status->isActive())->toBeTrue();
});

it('fires its events only after the commit', function (): void {
    Event::fake([OrderAwaitingHandover::class, OrderDisputed::class]);
    $order = orderIn(OrderStatus::CREATED);

    $this->transitions->awaitHandover($order, PaymentMethod::CASH);
    $this->transitions->dispute($order);

    Event::assertDispatched(OrderAwaitingHandover::class);
    Event::assertDispatched(OrderDisputed::class);
});

it('rolls the status back when the ledger posting fails', function (): void {
    $order = orderIn(OrderStatus::AWAITING_HANDOVER);
    $order->forceFill(['seller_id' => null])->save();

    expect(fn () => $this->transitions->complete($order, LedgerActor::system()))->toThrow(LogicException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::AWAITING_HANDOVER)
        ->and(LedgerTransaction::query()->count())->toBe(0);
});

it('talks to payments only through the gateway registry', function (): void {
    $gateways = app(PaymentGateways::class);

    expect($gateways->for(PaymentMethod::CASH))->toBeInstanceOf(CashGateway::class)
        ->and($gateways->for(PaymentMethod::CASH)->holdsFundsInEscrow())->toBeFalse()
        ->and($gateways->methods())->toBe([PaymentMethod::CASH]);
});
