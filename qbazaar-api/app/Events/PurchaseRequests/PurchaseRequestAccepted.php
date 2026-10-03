<?php

declare(strict_types=1);

namespace App\Events\PurchaseRequests;

/**
 * The seller accepted; the order is placed and the ad reserved.
 */
class PurchaseRequestAccepted extends PurchaseRequestEvent
{
    public function broadcastAs(): string
    {
        return 'purchase_request.accepted';
    }
}
