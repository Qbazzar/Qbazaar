<?php

declare(strict_types=1);

namespace App\Services\Admin\Ads;

use App\Data\Admin\AdDeal\OrderDeal;
use App\Models\Order;
use App\Services\Admin\TimelineEntry;
use Illuminate\Support\Collection;

/**
 * Assembles the deal of one order: tracker, payment state and timeline. The
 * ledger is read once (two queries plus one for the names of the actors)
 * whatever the order's history.
 */
final class OrderDealBuilder
{
    public function __construct(
        private readonly OrderProgress $progress,
        private readonly OrderPayment $payment,
        private readonly DealTimeline $timeline,
    ) {}

    /**
     * @param Collection<int, TimelineEntry>|null $statusMoves
     */
    public function build(Order $order, ?Collection $statusMoves = null, int $otherOrders = 0): OrderDeal
    {
        $transactions = $this->payment->transactionsOf($order);
        $names = $this->payment->actorNames($this->payment->actorIds($transactions));
        $summary = $this->payment->summarize($order, $transactions, $names);

        return new OrderDeal(
            order: $order,
            steps: $this->progress->stepsFor($order),
            payment: $summary,
            timeline: $this->timeline->build($order, $summary, $statusMoves),
            otherOrders: $otherOrders,
        );
    }
}
