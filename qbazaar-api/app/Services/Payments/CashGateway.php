<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Data\Ledger\LedgerActor;
use App\Enums\PaymentMethod;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Services\Ledger\LedgerRecipes;

/**
 * Cash on delivery or at handover. The buyer pays the seller in person, so
 * no money passes through the platform: the handover only books the
 * commission as a debt the seller owes, and a cancellation moves nothing.
 */
class CashGateway implements PaymentGateway
{
    public function __construct(
        private readonly LedgerRecipes $recipes,
    ) {}

    public function method(): PaymentMethod
    {
        return PaymentMethod::CASH;
    }

    public function holdsFundsInEscrow(): bool
    {
        return false;
    }

    public function settleHandover(Order $order, LedgerActor $actor): ?LedgerTransaction
    {
        return $this->recipes->chargeCommission($order, $actor);
    }

    public function settleCancellation(Order $order, LedgerActor $actor): ?LedgerTransaction
    {
        return null;
    }
}
