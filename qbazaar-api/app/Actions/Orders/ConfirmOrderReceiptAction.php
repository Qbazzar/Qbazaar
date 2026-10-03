<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Data\Ledger\LedgerActor;
use App\Enums\OrderStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderTransitionService;
use App\Services\Payments\PaymentGateways;

/**
 * The buyer of an escrow order confirms they received the item, which
 * releases the held money to the seller without waiting for the release
 * window. Cash orders are confirmed by the seller only.
 */
class ConfirmOrderReceiptAction
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
        private readonly PaymentGateways $gateways,
    ) {}

    public function __invoke(User $buyer, Order $order): Order
    {
        if ($buyer->id !== $order->buyer_id || ! $this->gateways->for($order->payment_method)->holdsFundsInEscrow()) {
            throw new DomainException(ErrorCode::ORDER_FORBIDDEN);
        }

        if ($order->handed_over_at === null) {
            throw new DomainException(ErrorCode::ORDER_INVALID_TRANSITION, details: [
                'from' => $order->status->value,
                'to' => OrderStatus::COMPLETED->value,
            ]);
        }

        return $this->transitions->complete($order, LedgerActor::user($buyer));
    }
}
