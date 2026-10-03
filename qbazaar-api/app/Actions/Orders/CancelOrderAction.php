<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Data\Ledger\LedgerActor;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderTransitionService;

/**
 * Either side cancels an order before the handover. The ad's reservation is
 * released, so it can take offers and orders again.
 */
class CancelOrderAction
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
    ) {}

    public function __invoke(User $actor, Order $order, ?string $reason): Order
    {
        if (! $order->isParticipant($actor)) {
            throw new DomainException(ErrorCode::ORDER_FORBIDDEN);
        }

        return $this->transitions->cancel($order, LedgerActor::user($actor), $actor->id, $reason);
    }
}
