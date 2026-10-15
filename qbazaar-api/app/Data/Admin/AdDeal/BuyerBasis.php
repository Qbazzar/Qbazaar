<?php

declare(strict_types=1);

namespace App\Data\Admin\AdDeal;

/**
 * Why the buyer card names the person it does.
 */
enum BuyerBasis: string
{
    /** The buyer of the platform order. */
    case ORDER = 'order';

    /** No order is visible to the viewer; the buyer of the accepted offer is the likely one. */
    case ACCEPTED_OFFER = 'accepted_offer';

    /** Sold with no platform order behind it. */
    case OFF_PLATFORM = 'off_platform';

    /** Nothing the viewer may read says who bought. */
    case UNKNOWN = 'unknown';
}
