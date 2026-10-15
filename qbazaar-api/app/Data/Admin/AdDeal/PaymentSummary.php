<?php

declare(strict_types=1);

namespace App\Data\Admin\AdDeal;

use Carbon\CarbonImmutable;

/**
 * The money state of one order, read from its ledger postings.
 *
 * `status`: pending, paid, refunded, reversed (handed over, then cancelled by a ruling
 * and the commission given back) or not_charged (cancelled before any payment).
 * `escrow`: none (cash, nothing held), held, released or refunded.
 * `payout` is where the seller's share stands: pending, paid_direct (cash at
 * handover), held, released (into the wallet), refunded, reversed or none.
 */
final readonly class PaymentSummary
{
    /**
     * @param list<PaymentLine> $lines
     */
    public function __construct(
        public string $status,
        public string $escrow,
        public string $payout,
        public ?CarbonImmutable $escrowAt,
        public ?string $escrowActor,
        public string $total,
        public string $commission,
        public string $sellerNet,
        public bool $commissionCharged,
        public bool $commissionRefunded,
        /** The seller's open commission across all their orders, not this order's share. */
        public string $sellerCommissionDebt,
        public array $lines,
    ) {}
}
