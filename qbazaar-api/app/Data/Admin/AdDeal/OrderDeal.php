<?php

declare(strict_types=1);

namespace App\Data\Admin\AdDeal;

use App\Models\Order;
use App\Services\Admin\TimelineEntry;
use Illuminate\Support\Collection;

/**
 * An order with everything the deal panel draws about it.
 */
final readonly class OrderDeal
{
    /**
     * @param list<OrderStep> $steps
     * @param Collection<int, TimelineEntry> $timeline
     */
    public function __construct(
        public Order $order,
        public array $steps,
        public PaymentSummary $payment,
        public Collection $timeline,
        public int $otherOrders = 0,
    ) {}
}
