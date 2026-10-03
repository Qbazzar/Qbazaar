<?php

declare(strict_types=1);

namespace App\Events\Orders;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The order was cancelled and the ad's reservation released. Dispatched after the commit.
 */
class OrderCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
