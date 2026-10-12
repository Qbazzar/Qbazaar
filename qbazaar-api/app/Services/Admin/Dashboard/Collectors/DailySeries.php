<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard\Collectors;

use App\Models\Ad;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\Dashboard\DashboardPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rows created per day over the last 30 days, zero-filled so every day has a
 * point. One grouped query per table on its `created_at` index.
 */
final class DailySeries
{
    public const int DAYS = 30;

    /**
     * @return list<array{date: string, count: int}>
     */
    public function users(CarbonImmutable $now): array
    {
        return $this->perDay(User::query(), $now);
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function ads(CarbonImmutable $now): array
    {
        return $this->perDay(Ad::query(), $now);
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function orders(CarbonImmutable $now): array
    {
        return $this->perDay(Order::query(), $now);
    }

    /**
     * Days are Qatar days: the database holds UTC, so each row is shifted by
     * the zone's fixed offset before its date is taken, and the first bucket
     * starts at Qatar midnight.
     *
     * @param Builder<*> $query
     * @return list<array{date: string, count: int}>
     */
    private function perDay(Builder $query, CarbonImmutable $now): array
    {
        $first = $now->setTimezone(DashboardPeriod::TIMEZONE)->startOfDay()->subDays(self::DAYS - 1);
        $day = $this->dayExpression($query->getModel()->getConnection()->getDriverName(), $first->offsetHours);

        $counts = $query
            ->where('created_at', '>=', $first->utc())
            ->selectRaw("{$day} as day, COUNT(*) as total")
            ->groupBy('day')
            ->toBase()
            ->pluck('total', 'day');

        return array_map(
            static fn (int $offset): array => [
                'date' => $first->addDays($offset)->toDateString(),
                'count' => (int) ($counts[$first->addDays($offset)->toDateString()] ?? 0),
            ],
            range(0, self::DAYS - 1),
        );
    }

    private function dayExpression(string $driver, int $offsetHours): string
    {
        return $driver === 'sqlite'
            ? "date(created_at, '+{$offsetHours} hours')"
            : "DATE(DATE_ADD(created_at, INTERVAL {$offsetHours} HOUR))";
    }
}
