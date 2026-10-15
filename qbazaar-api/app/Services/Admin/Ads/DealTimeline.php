<?php

declare(strict_types=1);

namespace App\Services\Admin\Ads;

use App\Data\Admin\AdDeal\PaymentSummary;
use App\Enums\LedgerTransactionType as Flow;
use App\Models\Order;
use App\Services\Admin\TimelineEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One time-ordered list of what happened to a deal: the order's own stamps,
 * the ledger postings and (on the ad page) the ad's status moves, newest first.
 */
final class DealTimeline
{
    public const int LIMIT = 12;

    private const array LEDGER_TONES = [
        Flow::ESCROW_RELEASED->value => 'success',
        Flow::ORDER_PAYMENT_RECEIVED->value => 'success',
        Flow::REFUND->value => 'warning',
        Flow::COMMISSION_REFUNDED->value => 'warning',
        Flow::REVERSAL->value => 'danger',
    ];

    /**
     * @param Collection<int, TimelineEntry>|null $statusMoves the ad's status history, when the page has it
     * @return Collection<int, TimelineEntry>
     */
    public function build(Order $order, PaymentSummary $payment, ?Collection $statusMoves = null): Collection
    {
        return $this->orderEvents($order)
            ->concat($this->ledgerEvents($payment))
            ->concat($statusMoves ?? [])
            ->sortByDesc(fn (TimelineEntry $entry): int => $entry->at->getTimestamp())
            ->take(self::LIMIT)
            ->values();
    }

    /**
     * @return Collection<int, TimelineEntry>
     */
    private function orderEvents(Order $order): Collection
    {
        $stamps = [
            ['created', $order->created_at, 'neutral'],
            ['awaiting_handover', $order->awaiting_handover_at, 'neutral'],
            ['handed_over', $order->handed_over_at, 'neutral'],
            ['completed', $order->completed_at, 'success'],
            ['cancelled', $order->cancelled_at, 'danger'],
            ['disputed', $order->disputed_at, 'warning'],
            ['dispute_resolved', $order->dispute_resolved_at, 'success'],
        ];

        return collect($stamps)
            ->filter(fn (array $stamp): bool => $stamp[1] !== null)
            ->map(fn (array $stamp): TimelineEntry => new TimelineEntry(
                title: (string) __('admin.deal.events.' . $stamp[0]),
                actor: null,
                at: $this->at($stamp[1]),
                tone: $stamp[2],
            ))
            ->values();
    }

    /**
     * @return Collection<int, TimelineEntry>
     */
    private function ledgerEvents(PaymentSummary $payment): Collection
    {
        return collect($payment->lines)->map(fn ($line): TimelineEntry => new TimelineEntry(
            title: (string) __('admin.deal.ledger.' . $line->type->value),
            actor: $line->actor,
            at: $line->at,
            tone: self::LEDGER_TONES[$line->type->value] ?? 'neutral',
        ));
    }

    private function at(Carbon $time): CarbonImmutable
    {
        return CarbonImmutable::instance($time);
    }
}
