<?php

declare(strict_types=1);

namespace App\Events\PurchaseRequests;

/**
 * The buyer changed the quantity or the note of a pending request.
 */
class PurchaseRequestUpdated extends PurchaseRequestEvent
{
    public function broadcastAs(): string
    {
        return 'purchase_request.updated';
    }
}
