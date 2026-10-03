<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Data\Ledger\LedgerActor;
use App\Enums\PaymentMethod;
use App\Models\LedgerTransaction;
use App\Models\Order;

/**
 * One way of paying for an order. The order flow only talks to this
 * interface, so an electronic gateway (M7) is a new implementation bound in
 * PaymentGateways, with no change to orders.
 *
 * The settle* hooks run inside the order's database transaction, after the
 * order and its ad are locked, so the ledger posting commits or rolls back
 * together with the status change.
 */
interface PaymentGateway
{
    public function method(): PaymentMethod;

    /**
     * Whether the buyer's money passes through the platform and is held in
     * escrow until the handover (bank transfer, card), or goes straight to
     * the seller (cash).
     */
    public function holdsFundsInEscrow(): bool;

    public function settleHandover(Order $order, LedgerActor $actor): ?LedgerTransaction;

    public function settleCancellation(Order $order, LedgerActor $actor): ?LedgerTransaction;
}
