<?php

declare(strict_types=1);

namespace App\Support\Sql;

/**
 * SQL that turns a UTC timestamp column into the date of the local day, week
 * (Monday) or month it falls in. The zone has a fixed offset, so the shift is
 * a plain hour addition and the grouped column keeps using its index range.
 * SQLite (CI) and MariaDB (production) spell the date functions differently.
 */
final class LocalBucket
{
    public const string DAY = 'day';

    public const string WEEK = 'week';

    public const string MONTH = 'month';

    public static function expression(string $driver, string $column, int $offsetHours, string $bucket = self::DAY): string
    {
        return $driver === 'sqlite'
            ? self::sqlite($column, $offsetHours, $bucket)
            : self::mysql($column, $offsetHours, $bucket);
    }

    private static function sqlite(string $column, int $hours, string $bucket): string
    {
        $shifted = "{$column}, '+{$hours} hours'";

        return match ($bucket) {
            self::WEEK => "date({$shifted}, '-6 days', 'weekday 1')",
            self::MONTH => "date({$shifted}, 'start of month')",
            default => "date({$shifted})",
        };
    }

    private static function mysql(string $column, int $hours, string $bucket): string
    {
        $shifted = "DATE_ADD({$column}, INTERVAL {$hours} HOUR)";

        return match ($bucket) {
            self::WEEK => "DATE(DATE_SUB({$shifted}, INTERVAL WEEKDAY({$shifted}) DAY))",
            self::MONTH => "DATE_FORMAT({$shifted}, '%Y-%m-01')",
            default => "DATE({$shifted})",
        };
    }
}
