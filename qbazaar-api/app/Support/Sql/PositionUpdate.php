<?php

declare(strict_types=1);

namespace App\Support\Sql;

use Illuminate\Support\Facades\DB;

/**
 * Renumbers an ordered list in one statement: the rows named by $orderedKeys
 * get positions 1..n in that order. A single CASE update instead of one
 * UPDATE per row, so reordering costs the same whatever the list length.
 * Table and column names are code constants; every value is bound.
 */
final class PositionUpdate
{
    /**
     * @param list<int|string> $orderedKeys
     */
    public static function apply(string $table, string $keyColumn, string $positionColumn, array $orderedKeys): void
    {
        if ($orderedKeys === []) {
            return;
        }

        $connection = DB::connection();
        $grammar = $connection->getQueryGrammar();
        $key = $grammar->wrap($keyColumn);

        $cases = [];
        $bindings = [];

        foreach ($orderedKeys as $index => $value) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $value;
            $bindings[] = $index + 1;
        }

        $sql = sprintf(
            'UPDATE %s SET %s = CASE %s %s END WHERE %s IN (%s)',
            $grammar->wrapTable($table),
            $grammar->wrap($positionColumn),
            $key,
            implode(' ', $cases),
            $key,
            implode(', ', array_fill(0, count($orderedKeys), '?')),
        );

        $connection->update($sql, [...$bindings, ...$orderedKeys]);
    }
}
