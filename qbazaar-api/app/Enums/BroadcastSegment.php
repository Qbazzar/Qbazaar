<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who a staff broadcast reaches. Every segment is limited to active
 * accounts; see BroadcastAudience for the exact queries.
 */
enum BroadcastSegment: string
{
    case ALL = 'all';
    case BUSINESSES = 'businesses';

    /** Users store no home city, so a city means sellers with a live ad there. */
    case CITY = 'city';

    public function label(): string
    {
        return (string) __('admin.broadcasts.segments.' . $this->value);
    }

    public function needsLocation(): bool
    {
        return $this === self::CITY;
    }
}
