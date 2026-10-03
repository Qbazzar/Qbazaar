<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What an order was created from: an accepted chat offer, or an accepted
 * "Buy Now" purchase request (BE-14.34).
 */
enum OrderSource: string
{
    case OFFER = 'offer';
    case PURCHASE_REQUEST = 'purchase_request';
}
