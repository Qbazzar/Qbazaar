<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Data\Ledger\LedgerActor;
use App\Enums\AdStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\Orders\OrderAwaitingHandover;
use App\Events\Orders\OrderCancelled;
use App\Events\Orders\OrderCompleted;
use App\Events\Orders\OrderDisputed;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Order;
use App\Services\Ads\AdLifecycleService;
use App\Services\Payments\PaymentGateways;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of `orders.status`.
 *
 * Each transition runs in one database transaction that locks the ad, then
 * the order, then (through the ledger) the touched accounts. That is the
 * same ad-first order the offer flow uses, so an order and an offer on one
 * ad never deadlock. The order is re-read under the lock, the move is
 * checked against OrderStatus::canTransitionTo(), the payment gateway's
 * ledger posting commits with it, and events fire after the commit.
 */
class OrderTransitionService
{
    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly AdLifecycleService $ads,
    ) {}

    /**
     * Checkout is done: the buyer chose how to pay and the order now waits
     * for the handover. Called by the checkout flow (BE-14.37).
     */
    public function awaitHandover(Order $order, PaymentMethod $method): Order
    {
        return $this->transition($order, function (Order $locked) use ($method): Closure {
            $this->gateways->for($method);
            $this->moveTo($locked, OrderStatus::AWAITING_HANDOVER, ['payment_method' => $method]);

            return fn () => OrderAwaitingHandover::dispatch($locked);
        });
    }

    /**
     * The handover happened: the gateway posts its ledger entries and the ad
     * is marked sold. Confirming an order that is already completed changes
     * nothing, so a retried request never posts twice. A disputed order is
     * completed only when an admin resolves the dispute.
     */
    public function complete(Order $order, LedgerActor $actor, bool $resolvingDispute = false): Order
    {
        return $this->transition($order, function (Order $locked, ?Ad $ad) use ($actor, $resolvingDispute): ?Closure {
            if ($locked->status === OrderStatus::COMPLETED) {
                return null;
            }

            if ($locked->status === OrderStatus::DISPUTED && ! $resolvingDispute) {
                throw $this->invalidTransition($locked->status, OrderStatus::COMPLETED);
            }

            $this->moveTo($locked, OrderStatus::COMPLETED);
            $this->gateways->for($locked->payment_method)->settleHandover($locked, $actor);

            if ($ad !== null && ! $ad->trashed() && $ad->status->canTransitionTo(AdStatus::SOLD)) {
                $this->ads->markSold($ad);
            }

            return fn () => OrderCompleted::dispatch($locked);
        });
    }

    /**
     * Cancelling an already cancelled order changes nothing. Like
     * completion, a disputed order is cancelled only by an admin's ruling.
     */
    public function cancel(Order $order, LedgerActor $actor, ?string $cancelledBy, ?string $reason, bool $resolvingDispute = false): Order
    {
        return $this->transition($order, function (Order $locked, ?Ad $ad) use ($actor, $cancelledBy, $reason, $resolvingDispute): ?Closure {
            if ($locked->status === OrderStatus::CANCELLED) {
                return null;
            }

            if ($locked->status === OrderStatus::DISPUTED && ! $resolvingDispute) {
                throw $this->invalidTransition($locked->status, OrderStatus::CANCELLED);
            }

            $this->moveTo($locked, OrderStatus::CANCELLED, [
                'cancelled_by' => $cancelledBy,
                'cancellation_reason' => $reason,
            ]);
            $this->gateways->for($locked->payment_method)->settleCancellation($locked, $actor);

            if ($ad !== null && ! $ad->trashed()) {
                $this->ads->releaseReservation($ad);
            }

            return fn () => OrderCancelled::dispatch($locked);
        });
    }

    public function dispute(Order $order): Order
    {
        return $this->transition($order, function (Order $locked): Closure {
            $this->moveTo($locked, OrderStatus::DISPUTED);

            return fn () => OrderDisputed::dispatch($locked);
        });
    }

    /**
     * @param Closure(Order, Ad|null): (Closure|null) $change returns the post-commit side effect, if any
     */
    private function transition(Order $order, Closure $change): Order
    {
        $locked = DB::transaction(function () use ($order, $change): Order {
            $ad = $this->lockAd($order);

            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $afterCommit = $change($locked, $ad);

            if ($afterCommit !== null) {
                DB::afterCommit($afterCommit);
            }

            return $locked;
        });

        $order->setRawAttributes($locked->getAttributes(), true);

        return $locked;
    }

    private function lockAd(Order $order): ?Ad
    {
        $adId = Order::query()->whereKey($order->getKey())->value('ad_id');

        if ($adId === null) {
            return null;
        }

        /** @var Ad|null */
        return Ad::withTrashed()->whereKey($adId)->lockForUpdate()->first();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function moveTo(Order $order, OrderStatus $next, array $attributes = []): void
    {
        if (! $order->status->canTransitionTo($next)) {
            throw $this->invalidTransition($order->status, $next);
        }

        $column = $next->timestampColumn();

        if ($column !== null) {
            $attributes[$column] = now();
        }

        $order->forceFill(['status' => $next, ...$attributes])->save();
    }

    private function invalidTransition(OrderStatus $from, OrderStatus $to): DomainException
    {
        return new DomainException(ErrorCode::ORDER_INVALID_TRANSITION, details: ['from' => $from->value, 'to' => $to->value]);
    }
}
