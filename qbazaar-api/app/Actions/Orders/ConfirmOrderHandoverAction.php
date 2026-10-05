<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Data\Ledger\LedgerActor;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderTransitionService;
use App\Services\Payments\PaymentGateways;

/**
 * The seller confirms the item was handed over. A cash order is completed
 * at once, booking the commission as the seller's debt in the same
 * transaction. An escrow order only records the handover: the buyer's
 * money is released when the buyer confirms or the release window ends.
 */
class ConfirmOrderHandoverAction
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
        private readonly PaymentGateways $gateways,
    ) {}

    public function __invoke(User $actor, Order $order): Order
    {
        if ($actor->id !== $order->seller_id) {
            throw new DomainException(ErrorCode::ORDER_FORBIDDEN);
        }

        if ($this->gateways->for($order->payment_method)->holdsFundsInEscrow()) {
            return $this->transitions->recordHandover($order);
        }

        return $this->transitions->complete($order, LedgerActor::user($actor));
    }
}
