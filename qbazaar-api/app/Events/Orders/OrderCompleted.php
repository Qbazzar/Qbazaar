<?php

declare(strict_types=1);

namespace App\Events\Orders;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The handover was confirmed and the order's ledger posting committed. Dispatched after the commit.
 */
class OrderCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
