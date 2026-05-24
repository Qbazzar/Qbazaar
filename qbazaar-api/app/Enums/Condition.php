<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Wear level of the listed item.
 *
 * Three-tier model matches the Bazzar UX mockups and OpenAPI schema
 * (qbazaar-contracts) — keep these wire values stable.
 */
enum Condition: string
{
    case NEW = 'new';
    case LIKE_NEW = 'like_new';
    case USED = 'used';
}
