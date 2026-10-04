<?php

declare(strict_types=1);

namespace App\Events\PurchaseRequests;

/**
 * The buyer sent a request; the seller is notified.
 */
class PurchaseRequestCreated extends PurchaseRequestEvent
{
    public function broadcastAs(): string
    {
        return 'purchase_request.created';
    }
}
