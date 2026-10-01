<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sort orders accepted by the public ad lists. DISTANCE needs a reference
 * point, so only search (which has lat/lng) accepts it.
 */
enum AdSort: string
{
    case LATEST = 'latest';
    case OLDEST = 'oldest';
    case PRICE_ASC = 'price_asc';
    case PRICE_DESC = 'price_desc';
    case MOST_VIEWED = 'most_viewed';
    case DISTANCE = 'distance';

    /**
     * @return list<string>
     */
    public static function feedValues(): array
    {
        return array_values(array_map(
            static fn (self $sort): string => $sort->value,
            array_filter(self::cases(), static fn (self $sort): bool => $sort !== self::DISTANCE),
        ));
    }
}
