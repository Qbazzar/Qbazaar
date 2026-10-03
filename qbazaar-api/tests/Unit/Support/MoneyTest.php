<?php

declare(strict_types=1);

use App\Support\Money;

it('normalises exact amounts to two decimals', function (string|int $input, string $expected): void {
    expect(Money::of($input))->toBe($expected);
})->with([
    ['10', '10.00'],
    ['10.1', '10.10'],
    [7, '7.00'],
    ['0.10000', '0.10'],
]);

it('refuses amounts with more than two decimals', function (): void {
    Money::of('1.005');
})->throws(InvalidArgumentException::class);

it('rounds half-up to two decimals', function (string $input, string $expected): void {
    expect(Money::round($input))->toBe($expected);
})->with([
    ['2.345', '2.35'],
    ['2.344', '2.34'],
    ['2.3449999', '2.34'],
    ['-2.345', '-2.35'],
    ['30.299999999999997', '30.30'],
]);

it('takes a percent of an amount with the one rounding rule', function (string $amount, string $percent, string $expected): void {
    expect(Money::percentOf($amount, $percent))->toBe($expected);
})->with([
    'whole' => ['200.00', '5.00', '10.00'],
    'half cent rounds up' => ['10.10', '5.00', '0.51'],
    'just below half' => ['10.09', '5.00', '0.50'],
    'fractional rate' => ['1999.99', '2.50', '50.00'],
    'zero rate' => ['500.00', '0.00', '0.00'],
    'full rate' => ['123.45', '100.00', '123.45'],
]);

it('adds, subtracts and compares without floats', function (): void {
    expect(Money::add('0.10', '0.20'))->toBe('0.30')
        ->and(Money::subtract('0.30', '0.10'))->toBe('0.20')
        ->and(Money::multiply('19.99', 3))->toBe('59.97')
        ->and(Money::compare('0.30', Money::add('0.10', '0.20')))->toBe(0)
        ->and(Money::isNegative(Money::subtract('1.00', '1.01')))->toBeTrue()
        ->and(Money::isZero('0.00'))->toBeTrue();
});
