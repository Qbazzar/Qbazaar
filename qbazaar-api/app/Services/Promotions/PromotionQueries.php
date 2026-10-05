<?php

declare(strict_types=1);

namespace App\Services\Promotions;

use App\Enums\PromotionStatus;
use App\Models\AdPromotion;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Promotion reads, each served by one index range.
 */
class PromotionQueries
{
    /**
     * The owner's promotions, newest first ((user_id, created_at) index).
     *
     * @return CursorPaginator<int, AdPromotion>
     */
    public function forOwner(User $owner, int $perPage): CursorPaginator
    {
        return AdPromotion::query()
            ->where('user_id', $owner->id)
            ->with('ad:id,title')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    /**
     * Bank transfers waiting for an admin, oldest first ((status, created_at) index).
     *
     * @return CursorPaginator<int, AdPromotion>
     */
    public function awaitingTransferConfirmation(int $perPage): CursorPaginator
    {
        return AdPromotion::query()
            ->where('status', PromotionStatus::PENDING_PAYMENT->value)
            ->with(['ad:id,title', 'user:id,full_name,phone'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate($perPage);
    }
}
