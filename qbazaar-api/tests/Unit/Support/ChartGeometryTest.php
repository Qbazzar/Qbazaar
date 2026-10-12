<?php

declare(strict_types=1);

use App\Support\Charts\Geometry;

it('builds a zero-based axis with tidy ticks', function (int|float $max, float $top, float $step): void {
    expect(Geometry::axis($max))->toBe(['top' => $top, 'step' => $step]);
})->with([
    'nothing' => [0, 1.0, 1.0],
    'one' => [1, 1.0, 1.0],
    'seven' => [7, 8.0, 2.0],
    'ninety' => [90, 100.0, 25.0],
]);

it('never needs more than four tick gaps', function (): void {
    foreach ([3, 13, 37, 120, 999, 12345] as $max) {
        $axis = Geometry::axis($max);
        expect($axis['top'] / $axis['step'])->toBeLessThanOrEqual(4.0)->and($axis['top'])->toBeGreaterThanOrEqual($max);
    }
});

it('puts zero on the baseline and the axis top on the ceiling', function (): void {
    $points = Geometry::points([0, 5, 10], 10.0, 100.0, 50.0, false);

    expect($points[0])->toBe(['x' => 0.0, 'y' => 50.0])
        ->and($points[2])->toBe(['x' => 100.0, 'y' => 0.0]);
});

it('mirrors x for right-to-left pages', function (): void {
    $points = Geometry::points([1, 2], 2.0, 100.0, 10.0, true);

    expect($points[0]['x'])->toBe(100.0)->and($points[1]['x'])->toBe(0.0);
});

it('closes the area down to the baseline', function (): void {
    $line = Geometry::line([1, 2, 3], 3.0, 20.0, 10.0, false);

    expect($line['line'])->toStartWith('M0 6.67')->and($line['area'])->toEndWith('L0 10Z');
});

it('splits a donut by share and skips empty slices', function (): void {
    $arcs = Geometry::donut([3, 0, 1], 10.0, 0.0);

    expect($arcs)->toHaveCount(2)
        ->and($arcs[0]['share'])->toBe(0.75)
        ->and($arcs[1]['index'])->toBe(2)
        ->and(Geometry::donut([0, 0], 10.0))->toBe([]);
});

it('keeps a lone slice a full ring', function (): void {
    expect(Geometry::donut([5], 10.0)[0]['length'])->toBe(round(2 * M_PI * 10, 3));
});

it('picks evenly spread tick indexes', function (): void {
    expect(Geometry::ticks(30, 5))->toBe([0, 7, 15, 22, 29])->and(Geometry::ticks(3, 5))->toBe([0, 1, 2]);
});
