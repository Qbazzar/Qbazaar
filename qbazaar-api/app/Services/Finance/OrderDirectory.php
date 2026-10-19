<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Every order, newest first, for the admin orders list (AD-17.7). A status
 * filter reads orders_status_created_idx; the unfiltered list reads
 * orders_created_status_idx backwards; an order id is a primary-key lookup.
 */
class OrderDirectory
{
    private const int PER_PAGE = 25;

    /**
     * @return CursorPaginator<int, Order>
     */
    public function latest(?OrderStatus $status, ?string $orderId): CursorPaginator
    {
        return Order::query()
            ->when($status, fn ($query, OrderStatus $status) => $query->where('status', $status->value))
            ->when($orderId, fn ($query, string $orderId) => $query->whereKey($orderId))
            ->with(['buyer:id,full_name,email', 'seller:id,full_name,email', 'buyer.avatarMedia', 'seller.avatarMedia'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @return array<string, string> status value => label, for the filter
     */
    public function statusOptions(): array
    {
        $options = [];
        foreach (OrderStatus::cases() as $status) {
            $options[$status->value] = (string) __('admin.deal.order_status.' . $status->value);
        }

        return $options;
    }
}
