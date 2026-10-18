<?php

declare(strict_types=1);

use App\Support\ManualOrder;

it('sorts the rows by the typed positions', function (): void {
    expect(ManualOrder::resolve(['a', 'b', 'c'], ['a' => 3, 'b' => 1, 'c' => 2]))->toBe(['b', 'c', 'a']);
});

it('moves a row typed up in front of the row already at that position', function (): void {
    expect(ManualOrder::resolve(['a', 'b', 'c'], ['a' => 1, 'b' => 2, 'c' => 1]))->toBe(['c', 'a', 'b']);
});

it('moves a row typed down behind the row already at that position', function (): void {
    expect(ManualOrder::resolve(['a', 'b', 'c'], ['a' => 3, 'b' => 2, 'c' => 3]))->toBe(['b', 'c', 'a']);
});

it('keeps rows without a valid position in their current place', function (): void {
    expect(ManualOrder::resolve(['a', 'b', 'c'], ['a' => null, 'b' => 'first', 'c' => 0]))->toBe(['a', 'b', 'c'])
        ->and(ManualOrder::resolve(['a', 'b', 'c'], []))->toBe(['a', 'b', 'c']);
});

it('ignores keys the list does not hold and never drops a row', function (): void {
    expect(ManualOrder::resolve([10, 20, 30], [30 => 1, 99 => 1]))->toBe([30, 10, 20]);
});

it('places positions past the end at the end', function (): void {
    expect(ManualOrder::resolve(['a', 'b', 'c'], ['a' => 50]))->toBe(['b', 'c', 'a']);
});
