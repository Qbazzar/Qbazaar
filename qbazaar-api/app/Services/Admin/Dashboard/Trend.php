<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * A figure with the one before it: the percentage change is computed with
 * exact decimals, so money never passes through a float on its way to a tile.
 */
final class Trend
{
    /**
     * @return array{value: int|string, previous: int|string, change: int|null}
     */
    public static function between(int|string $current, int|string $previous): array
    {
        $before = BigDecimal::of($previous);
        $change = $before->isZero()
            ? null
            : BigDecimal::of($current)->minus($before)->multipliedBy(100)->dividedBy($before, 0, RoundingMode::HALF_UP)->toInt();

        return ['value' => $current, 'previous' => $previous, 'change' => $change];
    }
}
