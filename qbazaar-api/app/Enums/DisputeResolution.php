<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * An admin's ruling on a disputed order: the sale stands, or it is
 * cancelled. The value is the order status the ruling moves it to.
 */
enum DisputeResolution: string
{
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
