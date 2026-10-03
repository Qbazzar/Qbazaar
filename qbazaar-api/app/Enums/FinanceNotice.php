<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The money events a user is told about. Each one is a notification
 * category and a key in lang/{locale}/messages.php `finance_notifications`.
 */
enum FinanceNotice: string
{
    case SETTLEMENT_SUBMITTED = 'settlement_submitted';
    case SETTLEMENT_APPROVED = 'settlement_approved';
    case SETTLEMENT_REJECTED = 'settlement_rejected';
    case WITHDRAWAL_REQUESTED = 'withdrawal_requested';
    case WITHDRAWAL_PAID = 'withdrawal_paid';
    case WITHDRAWAL_REJECTED = 'withdrawal_rejected';

    public function category(): string
    {
        return 'wallet.' . $this->value;
    }
}
