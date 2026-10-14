<?php

declare(strict_types=1);

namespace App\Services\Finance\Revenue;

use Carbon\CarbonImmutable;

/**
 * Everything the revenue page shows that can be cached: plain values only,
 * amounts as two-decimal strings. The latest lines are not in it because they
 * are paged live.
 */
final readonly class RevenueReport
{
    /**
     * @param list<array{date: string, income: string, deductions: string, net: string}> $series one point per bucket
     * @param list<array{id: string|null, name: array<string, string>|null, income: string, lines: int}> $categories
     * @param list<array{id: string, name: string, commission: string, orders: int}>|null $sellers null when the viewer may not see sellers
     */
    public function __construct(
        public RevenueWindow $window,
        public IncomeStatement $current,
        public IncomeStatement $previous,
        public string $escrowHeld,
        public string $payoutsPending,
        public array $series,
        public array $categories,
        public ?array $sellers,
        public CarbonImmutable $generatedAt,
        public ?string $revenueAccountId,
    ) {}
}
