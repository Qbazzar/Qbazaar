<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard\Collectors;

use App\Enums\OrderStatus;
use App\Models\Ad;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\Dashboard\DashboardPeriod;
use App\Support\Money;
use App\Support\Sql\LocalBucket;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rows created per day over the last 30 days, zero-filled so every day has a
 * point. One grouped query per series, each on an indexed time column. The
 * completed-order money series is bucketed by completion day, like the tiles.
 */
final class DailySeries
{
    public const int DAYS = 30;

    /**
     * @return list<array{date: string, count: int}>
     */
    public function users(CarbonImmutable $now): array
    {
        return $this->counts(User::query(), $now);
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function ads(CarbonImmutable $now): array
    {
        return $this->counts(Ad::query(), $now);
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function orders(CarbonImmutable $now): array
    {
        return $this->counts(Order::query(), $now);
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function messages(CarbonImmutable $now): array
    {
        return $this->counts(Message::query(), $now);
    }

    /**
     * GMV and commission of the orders completed each day, as decimal strings.
     *
     * @return list<array{date: string, gmv: string, commission: string}>
     */
    public function revenue(CarbonImmutable $now): array
    {
        $first = $this->firstDay($now);
        $rows = $this->grouped(
            Order::query()->where('status', OrderStatus::COMPLETED->value),
            $first,
            'completed_at',
            'SUM(total) as gmv, SUM(commission_amount) as commission',
        );

        return $this->fill($first, static fn (string $date): array => [
            'date' => $date,
            'gmv' => Money::round((string) ($rows[$date]->gmv ?? 0)),
            'commission' => Money::round((string) ($rows[$date]->commission ?? 0)),
        ]);
    }

    /**
     * @param Builder<*> $query
     * @return list<array{date: string, count: int}>
     */
    private function counts(Builder $query, CarbonImmutable $now): array
    {
        $first = $this->firstDay($now);
        $rows = $this->grouped($query, $first, 'created_at', 'COUNT(*) as total');

        return $this->fill($first, static fn (string $date): array => ['date' => $date, 'count' => (int) ($rows[$date]->total ?? 0)]);
    }

    private function firstDay(CarbonImmutable $now): CarbonImmutable
    {
        return $now->setTimezone(DashboardPeriod::TIMEZONE)->startOfDay()->subDays(self::DAYS - 1);
    }

    /**
     * Days are Qatar days: the database holds UTC, so each row is shifted by
     * the zone's fixed offset before its date is taken, and the first bucket
     * starts at Qatar midnight.
     *
     * @param Builder<*> $query
     * @return array<string, object> keyed by Qatar date
     */
    private function grouped(Builder $query, CarbonImmutable $first, string $column, string $aggregates): array
    {
        $day = $this->dayExpression($query->getModel()->getConnection()->getDriverName(), $column, $first->offsetHours);

        return $query
            ->where($column, '>=', $first->utc())
            ->selectRaw("{$day} as day, {$aggregates}")
            ->groupBy('day')
            ->toBase()
            ->get()
            ->keyBy('day')
            ->all();
    }

    /**
     * @template T of array<string, mixed>
     * @param callable(string): T $point
     * @return list<T>
     */
    private function fill(CarbonImmutable $first, callable $point): array
    {
        return array_map(
            static fn (int $offset): array => $point($first->addDays($offset)->toDateString()),
            range(0, self::DAYS - 1),
        );
    }

    private function dayExpression(string $driver, string $column, int $offsetHours): string
    {
        return LocalBucket::expression($driver, $column, $offsetHours);
    }
}
