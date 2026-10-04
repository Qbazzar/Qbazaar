<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\FinanceReview;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\FinanceStaff;
use App\Services\Orders\OrderDisputeWindow;
use App\Services\Orders\OrderTransitionService;

/**
 * The buyer reports a problem after the handover, which opens a dispute
 * for an admin to rule on. Finance staff are told at once.
 */
class ReportOrderProblemAction
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
        private readonly OrderDisputeWindow $window,
        private readonly FinanceStaff $staff,
    ) {}

    public function __invoke(User $buyer, Order $order, string $reason): Order
    {
        if ($buyer->id !== $order->buyer_id) {
            throw new DomainException(ErrorCode::ORDER_FORBIDDEN);
        }

        if (! $this->window->isOpen($order)) {
            throw new DomainException(ErrorCode::DISPUTE_WINDOW_CLOSED);
        }

        $disputed = $this->transitions->dispute($order, $buyer->id, $reason);

        $this->staff->requestReview(FinanceReview::DISPUTE, $disputed->total);

        return $disputed;
    }
}
