<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard;

use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The current and the previous time range of one dashboard request, with the
 * SQL that sums rows into both in a single pass.
 */
final readonly class DashboardWindow
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public CarbonImmutable $previousStart,
        public CarbonImmutable $previousEnd,
    ) {}

    /**
     * Rows of $column inside the current range and inside the previous one,
     * as `current` and `previous` aggregates. Pass `$sum` to add a column
     * instead of counting rows.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    public function selectSums(string $column, ?string $sum = null): array
    {
        $amount = $sum ?? '1';

        return [
            "COALESCE(SUM(CASE WHEN {$column} >= ? THEN {$amount} ELSE 0 END), 0) as current_total, "
            . "COALESCE(SUM(CASE WHEN {$column} >= ? AND {$column} < ? THEN {$amount} ELSE 0 END), 0) as previous_total",
            [$this->start, $this->previousStart, $this->previousEnd],
        ];
    }

    /**
     * @param Builder<*> $query
     * @return array{value: int|string, previous: int|string, change: int|null}
     */
    public function trend(Builder $query, string $column = 'created_at', ?string $sum = null): array
    {
        [$sql, $bindings] = $this->selectSums($column, $sum);
        $row = $query->where($column, '>=', $this->previousStart)->selectRaw($sql, $bindings)->toBase()->get()->sole();
        $format = $sum === null ? static fn ($v): int => (int) $v : static fn ($v): string => Money::round((string) $v);

        return Trend::between($format($row->current_total), $format($row->previous_total));
    }
}
