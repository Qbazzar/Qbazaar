<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How the item reaches the buyer, as chosen on the sell form.
 */
enum AdShipping: string
{
    case PICKUP_ONLY = 'pickup_only';
    case DELIVERY = 'delivery';
}
