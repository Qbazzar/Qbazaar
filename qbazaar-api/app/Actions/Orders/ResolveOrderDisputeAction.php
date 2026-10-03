<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\DisputeResolution;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderTransitionService;

/**
 * An admin rules on a disputed order with a written reason. The ruling
 * and the reason are kept on the order and in the activity log.
 */
class ResolveOrderDisputeAction
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
    ) {}

    public function __invoke(User $admin, Order $order, DisputeResolution $ruling, string $note): Order
    {
        $resolved = $this->transitions->resolveDispute($order, $ruling, $admin, $note);

        activity('orders')
            ->causedBy($admin)
            ->performedOn($resolved)
            ->event('dispute_resolved')
            ->withProperties(['resolution' => $ruling->value, 'note' => $note])
            ->log('Order dispute resolved');

        return $resolved;
    }
}
