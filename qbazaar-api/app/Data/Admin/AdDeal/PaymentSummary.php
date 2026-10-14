<?php

declare(strict_types=1);

namespace App\Data\Admin\AdDeal;

use Carbon\CarbonImmutable;

/**
 * The money state of one order, read from its ledger postings.
 *
 * `status`: pending, paid, refunded or not_charged (cancelled before any payment).
 * `escrow`: none (cash, nothing held), held, released or refunded.
 * `payout` is where the seller's share stands: pending, paid_direct (cash at
 * handover), held, released (into the wallet), refunded or none.
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
        public string $sellerCommissionDebt,
        public array $lines,
    ) {}
}
