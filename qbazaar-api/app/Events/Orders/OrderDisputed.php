<?php

declare(strict_types=1);

namespace App\Events\Orders;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A participant reported a problem with the handover; an admin resolves it. Dispatched after the commit.
 */
class OrderDisputed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
