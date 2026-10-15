<?php

declare(strict_types=1);

namespace App\Services\Finance\Revenue;

use App\Support\Money;

/**
 * What the platform earned from one order, straight from the ledger: the
 * commission it charged, the part it gave back, and what is left.
 */
final readonly class OrderPlatformIncome
{
    public function __construct(
        public string $orderId,
        public string $commission,
        public string $refunded,
    ) {}

    /** False while the ledger has not charged any commission for the order. */
    public function isRecorded(): bool
    {
        return Money::isPositive($this->commission) || Money::isPositive($this->refunded);
    }

    public function net(): string
    {
        return Money::subtract($this->commission, $this->refunded);
    }
}
