<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard;

use Carbon\CarbonImmutable;

/**
 * The window the dashboard tiles cover, and the equally long one before it
 * that their trend arrows compare against.
 *
 * Days are Qatar days: "today" starts at local midnight, and 7d / 30d are that
 * many whole local days ending now. The database stores UTC, so the window
 * converts its bounds before they reach a query.
 */
enum DashboardPeriod: string
{
    case TODAY = 'today';
    case WEEK = '7d';
    case MONTH = '30d';

    public const string TIMEZONE = 'Asia/Qatar';

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::WEEK;
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE);
    }

    public function window(CarbonImmutable $now): DashboardWindow
    {
        $now = $now->setTimezone(self::TIMEZONE);
        $start = $now->startOfDay()->subDays($this->days() - 1);

        return new DashboardWindow(
            start: $start,
            end: $now,
            previousStart: $start->subDays($this->days()),
            // Shifted by the same span as the start, so "today" is compared with
            // yesterday up to the same time of day.
            previousEnd: $now->subDays($this->days()),
        );
    }

    public function label(): string
    {
        return __('admin.home.periods.' . $this->value);
    }

    private function days(): int
    {
        return match ($this) {
            self::TODAY => 1,
            self::WEEK => 7,
            self::MONTH => 30,
        };
    }
}
