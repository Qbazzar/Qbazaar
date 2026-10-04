<?php

declare(strict_types=1);

namespace App\Events\PurchaseRequests;

/**
 * The buyer withdrew the request, or the platform closed it because the ad found another buyer or left the market.
 */
class PurchaseRequestCancelled extends PurchaseRequestEvent
{
    public function broadcastAs(): string
    {
        return 'purchase_request.cancelled';
    }
}
