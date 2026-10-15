<?php

declare(strict_types=1);

namespace App\Services\Finance\Revenue;

use App\Support\Money;

/**
 * The platform's income statement for one period, every amount a two-decimal
 * string. A line the ledger does not record yet is null, never zero: zero
 * would claim "nothing happened" when the truth is "we do not track it".
 *
 * Income is what the platform earned; deductions reduce it; net profit is the
 * difference (net operating profit). GMV, payouts and wallet adjustments are
 * shown beside it but are not part of it: an adjustment is a correction of a
 * user's balance by an admin, not an operating cost, and a clawback must never
 * raise profit.
 */
final readonly class IncomeStatement
{
    /**
     * @param array<string, string|null> $income amount per {@see IncomeSource} value
     */
    public function __construct(
        public string $gmv,
        public int $orders,
        public array $income,
        public string $refunds,
        public ?string $gatewayFees,
        public string $walletAdjustments,
        public string $payoutsPaid,
    ) {}

    public static function empty(): self
    {
        $income = [];
        foreach (IncomeSource::cases() as $source) {
            $income[$source->value] = $source->recorded() ? Money::ZERO : null;
        }

        return new self(Money::ZERO, 0, $income, Money::ZERO, IncomeFolder::gatewayFeesRecorded() ? Money::ZERO : null, Money::ZERO, Money::ZERO);
    }

    public function totalIncome(): string
    {
        return Money::add(Money::ZERO, ...array_values(array_filter($this->income, static fn (?string $v): bool => $v !== null)));
    }

    public function totalDeductions(): string
    {
        return Money::add($this->refunds, $this->gatewayFees ?? Money::ZERO);
    }

    /** Net operating profit: income minus refunds and gateway fees. Wallet adjustments never enter it. */
    public function netProfit(): string
    {
        return Money::subtract($this->totalIncome(), $this->totalDeductions());
    }

    /** Operating profit after the net wallet adjustments, for information only. */
    public function netAfterAdjustments(): string
    {
        return Money::subtract($this->netProfit(), $this->walletAdjustments);
    }
}
