<?php

declare(strict_types=1);

namespace App\Events\Orders;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Checkout is done and the order waits for the item and the payment to change hands. Dispatched after the commit.
 */
class OrderAwaitingHandover
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
