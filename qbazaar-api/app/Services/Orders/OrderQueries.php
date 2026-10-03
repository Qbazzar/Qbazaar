<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Order reads for participants. Purchases and sales are separate lists, so
 * each reads one index range ((buyer_id, created_at) or (seller_id,
 * created_at)) instead of OR-ing both columns.
 */
class OrderQueries
{
    /**
     * @param 'buyer'|'seller' $role
     * @return CursorPaginator<int, Order>
     */
    public function forParticipant(User $user, string $role, ?OrderStatus $status, int $perPage): CursorPaginator
    {
        return Order::query()
            ->where($role === 'seller' ? 'seller_id' : 'buyer_id', $user->id)
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status?->value))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    /**
     * The order when the user takes part in it, else null: a stranger cannot
     * tell a missing order from someone else's.
     */
    public function findForParticipant(string $orderId, User $user): ?Order
    {
        $order = Order::query()->find($orderId);

        return $order !== null && $order->isParticipant($user) ? $order : null;
    }
}
