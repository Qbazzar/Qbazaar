<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How the buyer receives the item, chosen at checkout. Delivery is offered
 * only when the ad's shipping is `delivery`; pickup is always possible.
 */
enum Fulfillment: string
{
    case PICKUP = 'pickup';
    case DELIVERY = 'delivery';
}
