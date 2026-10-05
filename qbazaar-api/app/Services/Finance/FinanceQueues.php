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
 * Each one reads a (status, created_at) or (status, disputed_at) index.
 */
class FinanceQueues
{
    private const int PER_PAGE = 25;

    /**
     * @return CursorPaginator<int, CommissionSettlement>
     */
    public function settlements(SettlementStatus $status): CursorPaginator
    {
        return CommissionSettlement::query()
            ->where('status', $status->value)
            ->with(['user:id,full_name,email,phone', 'reviewer:id,full_name', 'media'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @return CursorPaginator<int, Withdrawal>
     */
    public function withdrawals(WithdrawalStatus $status): CursorPaginator
    {
        return Withdrawal::query()
            ->where('status', $status->value)
            ->with(['user:id,full_name,email,phone', 'reviewer:id,full_name'])
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
            ->with(['buyer:id,full_name,email,phone', 'seller:id,full_name,email,phone'])
            ->orderBy('disputed_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
