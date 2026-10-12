<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard;

use Carbon\CarbonImmutable;

/**
 * Orders the "needs attention" queues by urgency and tags each with a
 * severity, so the view only has to render them in the given order.
 */
final class AttentionPriority
{
    public const string CRITICAL = 'critical';

    public const string WARNING = 'warning';

    public const string CLEAR = 'clear';

    /** A queue whose oldest entry has waited this long is critical. */
    private const int CRITICAL_AFTER_HOURS = 24;

    /** Queues that are urgent as soon as they are not empty. */
    private const array ALWAYS_URGENT = ['failed_jobs', 'disputes'];

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function sort(array $items, CarbonImmutable $now): array
    {
        $tagged = array_map(static fn (array $item): array => $item + ['severity' => self::severity($item, $now)], $items);
        $rank = [self::CRITICAL => 0, self::WARNING => 1, self::CLEAR => 2];

        usort($tagged, static fn (array $a, array $b): int => [$rank[$a['severity']], self::since($a), -$a['count']]
            <=> [$rank[$b['severity']], self::since($b), -$b['count']]);

        return $tagged;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function severity(array $item, CarbonImmutable $now): string
    {
        if ($item['count'] <= 0) {
            return self::CLEAR;
        }
        $waitedTooLong = $item['oldest'] !== null
            && CarbonImmutable::parse($item['oldest'])->lte($now->subHours(self::CRITICAL_AFTER_HOURS));

        return $waitedTooLong || in_array($item['key'], self::ALWAYS_URGENT, true) ? self::CRITICAL : self::WARNING;
    }

    /**
     * Oldest first; queues with no timestamp sort after those that have one.
     *
     * @param array<string, mixed> $item
     */
    private static function since(array $item): int
    {
        return $item['oldest'] === null ? PHP_INT_MAX : CarbonImmutable::parse($item['oldest'])->getTimestamp();
    }
}
