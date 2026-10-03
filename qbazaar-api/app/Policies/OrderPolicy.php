<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Who may act on an order. Whether its status allows the move is checked
 * under the lock in OrderTransitionService.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->isParticipant($user);
    }

    public function confirmHandover(User $user, Order $order): bool
    {
        return $user->id === $order->seller_id;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $order->isParticipant($user);
    }

    public function checkout(User $user, Order $order): bool
    {
        return $user->id === $order->buyer_id;
    }
}
