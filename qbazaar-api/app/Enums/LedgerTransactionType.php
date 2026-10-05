<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kinds of journal transaction. LEDGER.md in qbazaar-contracts lists the
 * debit and credit lines each one posts.
 */
enum LedgerTransactionType: string
{
    case ORDER_PAYMENT_RECEIVED = 'order_payment_received';
    case ESCROW_RELEASED = 'escrow_released';
    case COMMISSION_CHARGED = 'commission_charged';
    case COMMISSION_SETTLED = 'commission_settled';
    case WITHDRAWAL_REQUESTED = 'withdrawal_requested';
    case WITHDRAWAL_PAID = 'withdrawal_paid';
    case WITHDRAWAL_REJECTED = 'withdrawal_rejected';
    case REFUND = 'refund';
    case PROMOTION_PURCHASED = 'promotion_purchased';
    case ADJUSTMENT = 'adjustment';
    case REVERSAL = 'reversal';
    case COMMISSION_REFUNDED = 'commission_refunded';

    /**
     * The user-facing statement line, translated from lang/{locale}/ledger.php.
     */
    public function descriptionKey(): string
    {
        return 'ledger.descriptions.' . $this->value;
    }
}
