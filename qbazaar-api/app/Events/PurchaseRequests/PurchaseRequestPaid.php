<?php

declare(strict_types=1);

namespace App\Events\PurchaseRequests;

/**
 * The order placed from the request was handed over and paid.
 */
class PurchaseRequestPaid extends PurchaseRequestEvent
{
    public function broadcastAs(): string
    {
        return 'purchase_request.paid';
    }
}
