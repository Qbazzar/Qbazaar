<?php

declare(strict_types=1);

namespace App\Services\Admin\Ads;

use App\Data\Admin\AdDeal\OrderStep;
use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonImmutable;

/**
 * The tracker of an order, from the real OrderStatus states: created, then
 * awaiting handover, then completed, with disputed in between when the buyer
 * reported a problem and cancelled in place of completed when it was called off.
 */
final class OrderProgress
{
    /**
     * @return list<OrderStep>
     */
    public function stepsFor(Order $order): array
    {
        $keys = $this->keysFor($order);
        $current = array_search($order->status->value, $keys, true);

        return array_map(
            fn (string $key, int $index): OrderStep => new OrderStep($key, $this->stateOf($key, $index, (int) $current), $this->reachedAt($order, $key)),
            $keys,
            array_keys($keys),
        );
    }

    /**
     * @return list<string>
     */
    private function keysFor(Order $order): array
    {
        $keys = [OrderStatus::CREATED->value];

        if ($order->awaiting_handover_at !== null) {
            $keys[] = OrderStatus::AWAITING_HANDOVER->value;
        }

        if ($order->disputed_at !== null) {
            $keys[] = OrderStatus::DISPUTED->value;
        }

        $keys[] = $order->status === OrderStatus::CANCELLED ? OrderStatus::CANCELLED->value : OrderStatus::COMPLETED->value;

        return $this->withCurrent($order, $keys);
    }

    /**
     * An order created and still in checkout has no awaiting step yet, but a
     * live tracker shows what comes next.
     *
     * @param list<string> $keys
     * @return list<string>
     */
    private function withCurrent(Order $order, array $keys): array
    {
        if ($order->status === OrderStatus::CREATED && ! in_array(OrderStatus::AWAITING_HANDOVER->value, $keys, true)) {
            array_splice($keys, 1, 0, OrderStatus::AWAITING_HANDOVER->value);
        }

        return $keys;
    }

    private function stateOf(string $key, int $index, int $current): string
    {
        return match (true) {
            $index < $current => 'done',
            $index > $current => 'upcoming',
            $key === OrderStatus::CANCELLED->value => 'failed',
            $key === OrderStatus::DISPUTED->value => 'warning',
            $key === OrderStatus::COMPLETED->value => 'done',
            default => 'current',
        };
    }

    private function reachedAt(Order $order, string $key): ?CarbonImmutable
    {
        $at = match ($key) {
            OrderStatus::CREATED->value => $order->created_at,
            OrderStatus::AWAITING_HANDOVER->value => $order->awaiting_handover_at,
            OrderStatus::DISPUTED->value => $order->disputed_at,
            OrderStatus::COMPLETED->value => $order->completed_at,
            OrderStatus::CANCELLED->value => $order->cancelled_at,
            default => null,
        };

        return $at === null ? null : CarbonImmutable::instance($at);
    }
}
