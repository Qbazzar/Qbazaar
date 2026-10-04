<?php

declare(strict_types=1);

namespace App\Events\PurchaseRequests;

/**
 * The seller declined the request.
 */
class PurchaseRequestRejected extends PurchaseRequestEvent
{
    public function broadcastAs(): string
    {
        return 'purchase_request.rejected';
    }
}
