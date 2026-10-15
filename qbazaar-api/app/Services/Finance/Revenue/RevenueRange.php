<?php

declare(strict_types=1);

namespace App\Services\Finance\Revenue;

use App\Services\Admin\Dashboard\DashboardPeriod;
use Carbon\CarbonImmutable;

/**
 * The period choices of the revenue page. Days are Qatar days, like the admin
 * home: "today" starts at local midnight and the rolling ranges end now.
 */
enum RevenueRange: string
{
    case TODAY = 'today';
    case WEEK = '7d';
    case MONTH = '30d';
    case THIS_MONTH = 'this_month';
    case LAST_MONTH = 'last_month';
    case THIS_YEAR = 'this_year';
    case CUSTOM = 'custom';

    /** The longest custom range, so one request cannot sweep the whole ledger. */
    public const int MAX_CUSTOM_DAYS = 731;

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::MONTH;
    }

    public function label(): string
    {
        return __('admin.revenue.periods.' . $this->value);
    }

    /**
     * @param CarbonImmutable|null $from first day of a custom range (local date)
     * @param CarbonImmutable|null $to last day of a custom range, inclusive
     */
    public function window(CarbonImmutable $now, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): RevenueWindow
    {
        $now = $now->setTimezone(DashboardPeriod::TIMEZONE);
        $today = $now->startOfDay();

        [$start, $end] = match ($this) {
            self::TODAY => [$today, $now],
            self::WEEK => [$today->subDays(6), $now],
            self::MONTH => [$today->subDays(29), $now],
            self::THIS_MONTH => [$now->startOfMonth(), $now],
            self::LAST_MONTH => [$now->startOfMonth()->subMonthNoOverflow(), $now->startOfMonth()],
            self::THIS_YEAR => [$now->startOfYear(), $now],
            self::CUSTOM => $this->customBounds($now, $from, $to),
        };

        return new RevenueWindow($this, $start, $end, ...$this->previousBounds($start, $end));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function customBounds(CarbonImmutable $now, ?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $start = ($from ?? $now)->setTimezone(DashboardPeriod::TIMEZONE)->startOfDay();
        $end = ($to ?? $now)->setTimezone(DashboardPeriod::TIMEZONE)->startOfDay()->addDay();

        return [$start, $end->greaterThan($now) ? $now : $end];
    }

    /**
     * The like-for-like range the trend arrows compare with: the month before
     * for month ranges, the year before for this year, otherwise the same
     * number of whole days straight before.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function previousBounds(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return match ($this) {
            self::THIS_MONTH, self::LAST_MONTH => [$start->subMonthNoOverflow(), $this->earlier($end->subMonthNoOverflow(), $start)],
            self::THIS_YEAR => [$start->subYear(), $end->subYear()],
            default => $this->shiftedByDays($start, $end),
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function shiftedByDays(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $days = max(1, (int) ceil(($end->getTimestamp() - $start->getTimestamp()) / 86_400));

        return [$start->subDays($days), $end->subDays($days)];
    }

    private function earlier(CarbonImmutable $a, CarbonImmutable $b): CarbonImmutable
    {
        return $a->lessThan($b) ? $a : $b;
    }
}
