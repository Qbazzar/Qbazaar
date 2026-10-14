<?php

declare(strict_types=1);

namespace App\Data\Admin\AdDeal;

use App\Enums\LedgerTransactionType;
use Carbon\CarbonImmutable;

/**
 * A journal transaction posted for the order, as one row of the payment card.
 */
final readonly class PaymentLine
{
    public function __construct(
        public LedgerTransactionType $type,
        public CarbonImmutable $at,
        public ?string $actor,
        public string $amount,
    ) {}
}
