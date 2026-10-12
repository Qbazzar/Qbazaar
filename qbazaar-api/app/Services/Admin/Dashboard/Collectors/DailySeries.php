<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard\Collectors;

use App\Models\Ad;
use App\Models\Order;
use App\Models\User;
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
     * @param Builder<*> $query
     * @return list<array{date: string, count: int}>
     */
    private function perDay(Builder $query, CarbonImmutable $now): array
    {
        $first = $now->subDays(self::DAYS - 1)->startOfDay();

        $counts = $query
            ->where('created_at', '>=', $first)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
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
}
