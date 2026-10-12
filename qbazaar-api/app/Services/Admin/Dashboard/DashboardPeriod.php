<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard;

use Carbon\CarbonImmutable;
use DateInterval;

/**
 * The window the dashboard tiles cover, and the equally long one before it
 * that their trend arrows compare against.
 */
enum DashboardPeriod: string
{
    case TODAY = 'today';
    case WEEK = '7d';
    case MONTH = '30d';

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::WEEK;
    }

    public function window(CarbonImmutable $now): DashboardWindow
    {
        return new DashboardWindow(
            start: $this->start($now),
            end: $now,
            previousStart: $this->previousStart($now),
            previousEnd: $this->previousEnd($now),
        );
    }

    public function start(CarbonImmutable $now): CarbonImmutable
    {
        return match ($this) {
            self::TODAY => $now->startOfDay(),
            self::WEEK => $now->subDays(7),
            self::MONTH => $now->subDays(30),
        };
    }

    public function previousStart(CarbonImmutable $now): CarbonImmutable
    {
        return $this->start($now)->sub($this->length());
    }

    /** Shifted by the same span as the start, so "today" is compared with yesterday up to the same time of day. */
    public function previousEnd(CarbonImmutable $now): CarbonImmutable
    {
        return $now->sub($this->length());
    }

    public function label(): string
    {
        return __('admin.home.periods.' . $this->value);
    }

    private function length(): DateInterval
    {
        return match ($this) {
            self::TODAY => new DateInterval('P1D'),
            self::WEEK => new DateInterval('P7D'),
            self::MONTH => new DateInterval('P30D'),
        };
    }
}
