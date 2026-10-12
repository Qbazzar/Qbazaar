<?php

declare(strict_types=1);

namespace App\Support\Charts;

/**
 * The arithmetic behind the dashboard's inline-SVG charts, kept out of the
 * Blade files so it can be tested. Everything returns plain numbers/strings.
 */
final class Geometry
{
    /**
     * A round axis top and tick step so the scale starts at zero and labels
     * land on tidy numbers (never more than $intervals gaps).
     *
     * @return array{top: float, step: float}
     */
    public static function axis(float $max, int $intervals = 4, bool $integers = true): array
    {
        if ($max <= 0) {
            return ['top' => 1.0, 'step' => 1.0];
        }
        $raw = $max / $intervals;
        $magnitude = 10 ** floor(log10($raw));
        $step = $magnitude;
        foreach ([1, 2, 2.5, 5, 10] as $factor) {
            $step = $factor * $magnitude;
            if ($step >= $raw) {
                break;
            }
        }
        $step = $integers ? max(1.0, ceil($step)) : $step;

        return ['top' => ceil($max / $step) * $step, 'step' => $step];
    }

    /**
     * Line and area paths for one series in a $width x $height box, y growing
     * upwards from zero. $mirror flips x so the newest day sits at the start
     * edge in right-to-left pages.
     *
     * @param list<float|int> $values
     * @return array{line: string, area: string, points: list<array{x: float, y: float}>}
     */
    public static function line(array $values, float $top, float $width, float $height, bool $mirror): array
    {
        $points = self::points($values, $top, $width, $height, $mirror);
        if ($points === []) {
            return ['line' => '', 'area' => '', 'points' => []];
        }
        $line = '';
        foreach ($points as $index => $point) {
            $line .= ($index === 0 ? 'M' : 'L') . self::n($point['x']) . ' ' . self::n($point['y']);
        }
        $first = reset($points);
        $last = end($points);
        $area = $line . 'L' . self::n($last['x']) . ' ' . self::n($height) . 'L' . self::n($first['x']) . ' ' . self::n($height) . 'Z';

        return ['line' => $line, 'area' => $area, 'points' => $points];
    }

    /**
     * @param list<float|int> $values
     * @return list<array{x: float, y: float}>
     */
    public static function points(array $values, float $top, float $width, float $height, bool $mirror): array
    {
        $last = max(1, count($values) - 1);
        $points = [];
        foreach (array_values($values) as $index => $value) {
            $x = $index / $last * $width;
            $points[] = ['x' => $mirror ? $width - $x : $x, 'y' => $height - ($top > 0 ? $value / $top : 0) * $height];
        }

        return $points;
    }

    /**
     * Donut arcs as stroke-dasharray pieces of a circle of radius $radius.
     * Zero-value slices are skipped; $gap separates neighbours (not applied
     * to a single full ring).
     *
     * @param list<int|float> $values
     * @return list<array{index: int, length: float, offset: float, share: float}>
     */
    public static function donut(array $values, float $radius, float $gap = 2.0): array
    {
        $total = array_sum($values);
        $circumference = 2 * M_PI * $radius;
        $visible = count(array_filter($values, static fn ($v): bool => $v > 0));
        if ($total <= 0) {
            return [];
        }
        $segments = [];
        $offset = 0.0;
        foreach ($values as $index => $value) {
            if ($value <= 0) {
                continue;
            }
            $span = $value / $total * $circumference;
            $length = $visible > 1 ? max(0.5, $span - $gap) : $span;
            $segments[] = ['index' => $index, 'length' => round($length, 3), 'offset' => round($offset, 3), 'share' => $value / $total];
            $offset += $span;
        }

        return $segments;
    }

    /**
     * Evenly spaced indexes (first and last included) for x-axis labels.
     *
     * @return list<int>
     */
    public static function ticks(int $count, int $wanted): array
    {
        if ($count <= $wanted) {
            return range(0, max(0, $count - 1));
        }

        return array_values(array_unique(array_map(
            static fn (int $i): int => (int) round($i * ($count - 1) / ($wanted - 1)),
            range(0, $wanted - 1),
        )));
    }

    private static function n(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
