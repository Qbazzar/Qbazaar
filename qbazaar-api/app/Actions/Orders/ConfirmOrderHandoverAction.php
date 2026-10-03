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
 * The seller confirms the item was handed over and paid for. For a cash
 * order that books the commission as the seller's debt, in the same
 * transaction as the status change.
 */
class ConfirmOrderHandoverAction
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
    ) {}

    public function __invoke(User $actor, Order $order): Order
    {
        if ($actor->id !== $order->seller_id) {
            throw new DomainException(ErrorCode::ORDER_FORBIDDEN);
        }

        return $this->transitions->complete($order, LedgerActor::user($actor));
    }
}
