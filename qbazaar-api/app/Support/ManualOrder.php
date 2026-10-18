<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The new order of a hand-sorted list after staff typed positions next to
 * its rows. Rows are sorted by the position typed for them; a row with no
 * (or an invalid) position keeps its current place. On a tie, a row staff
 * moved up goes before the rows already there and a row moved down goes
 * after them, so typing "1" next to the third row makes it first even
 * though the first row still shows "1". The result is always a full,
 * stable permutation of the current keys.
 */
final class ManualOrder
{
    /**
     * @template TKey of int|string
     *
     * @param list<TKey> $currentOrder keys in their current order
     * @param array<array-key, mixed> $requested key => typed position (1 = first)
     * @return list<TKey>
     */
    public static function resolve(array $currentOrder, array $requested): array
    {
        $ranked = [];

        foreach ($currentOrder as $index => $key) {
            $current = $index + 1;
            $typed = $requested[$key] ?? null;
            $position = is_numeric($typed) && (int) $typed >= 1 ? (int) $typed : $current;

            $ranked[] = ['key' => $key, 'position' => $position, 'move' => $position <=> $current, 'index' => $index];
        }

        usort($ranked, static fn (array $a, array $b): int => [$a['position'], $a['move'], $a['index']] <=> [$b['position'], $b['move'], $b['index']]);

        return array_map(static fn (array $row): int|string => $row['key'], $ranked);
    }
}
