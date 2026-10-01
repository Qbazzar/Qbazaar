<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether the seller offers the item or is looking for one ("Offering" /
 * "Looking for" on the sell form).
 */
enum AdType: string
{
    case OFFERING = 'offering';
    case WANTED = 'wanted';
}
