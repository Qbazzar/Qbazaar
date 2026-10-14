<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\OrderStatus;
use App\Enums\SettlementStatus;
use App\Enums\WithdrawalStatus;
use App\Models\CommissionSettlement;
use App\Models\Order;
use App\Models\Withdrawal;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * The finance staff's work queues, oldest first so nothing waits longest.
 * Each one reads a (status, created_at) or (status, disputed_at) index; narrowed to one user
 * it reads that user's (user_id, created_at) index instead.
 */
class FinanceQueues
{
    private const int PER_PAGE = 25;

    /**
     * @return CursorPaginator<int, CommissionSettlement>
     */
    public function settlements(?SettlementStatus $status, ?string $userId = null): CursorPaginator
    {
        return CommissionSettlement::query()
            ->when($status, fn ($query, SettlementStatus $status) => $query->where('status', $status->value))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->with(['user:id,full_name,email,phone', 'reviewer:id,full_name', 'user.avatarMedia', 'reviewer.avatarMedia', 'media'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @return CursorPaginator<int, Withdrawal>
     */
    public function withdrawals(?WithdrawalStatus $status, ?string $userId = null): CursorPaginator
    {
        return Withdrawal::query()
            ->when($status, fn ($query, WithdrawalStatus $status) => $query->where('status', $status->value))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->with(['user:id,full_name,email,phone', 'reviewer:id,full_name', 'user.avatarMedia', 'reviewer.avatarMedia'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @return CursorPaginator<int, Order>
     */
    public function openDisputes(): CursorPaginator
    {
        return Order::query()
            ->where('status', OrderStatus::DISPUTED->value)
            ->with(['buyer:id,full_name,email,phone', 'seller:id,full_name,email,phone', 'buyer.avatarMedia', 'seller.avatarMedia'])
            ->orderBy('disputed_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
