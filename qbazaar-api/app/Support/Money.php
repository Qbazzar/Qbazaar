<?php

declare(strict_types=1);

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Decimal arithmetic for QAR amounts. Every value goes in and comes out as a
 * two-decimal string, so no amount ever passes through a float.
 *
 * {@see round()} is the only place a rounding rule is applied: half-up to
 * two decimals. Everything else is exact.
 */
final class Money
{
    public const int SCALE = 2;

    public const string ZERO = '0.00';

    /**
     * Normalises an exact amount to two decimals, refusing more precision
     * than a decimal(14,2) column can hold.
     */
    public static function of(BigNumber|int|string $amount): string
    {
        $decimal = BigDecimal::of($amount);

        if ($decimal->getScale() > self::SCALE && ! $decimal->isEqualTo($decimal->toScale(self::SCALE, RoundingMode::DOWN))) {
            throw new InvalidArgumentException("Amount [{$amount}] has more than two decimals.");
        }

        return (string) $decimal->toScale(self::SCALE);
    }

    public static function round(BigNumber|int|string $amount): string
    {
        return (string) BigDecimal::of($amount)->toScale(self::SCALE, RoundingMode::HALF_UP);
    }

    /**
     * The given percent of an amount, e.g. percentOf('1999.99', '2.50') = '50.00'.
     */
    public static function percentOf(string $amount, string $percent): string
    {
        return self::round(BigDecimal::of($amount)->multipliedBy($percent)->dividedBy(100, self::SCALE + 4, RoundingMode::HALF_UP));
    }

    public static function add(string ...$amounts): string
    {
        $sum = BigDecimal::zero();

        foreach ($amounts as $amount) {
            $sum = $sum->plus($amount);
        }

        return (string) $sum->toScale(self::SCALE);
    }

    public static function subtract(string $from, string $amount): string
    {
        return (string) BigDecimal::of($from)->minus($amount)->toScale(self::SCALE);
    }

    public static function multiply(string $amount, int $times): string
    {
        return (string) BigDecimal::of($amount)->multipliedBy($times)->toScale(self::SCALE);
    }

    public static function compare(string $left, string $right): int
    {
        return BigDecimal::of($left)->compareTo($right);
    }

    public static function isPositive(string $amount): bool
    {
        return BigDecimal::of($amount)->isPositive();
    }

    public static function isNegative(string $amount): bool
    {
        return BigDecimal::of($amount)->isNegative();
    }

    public static function isZero(string $amount): bool
    {
        return BigDecimal::of($amount)->isZero();
    }
}
