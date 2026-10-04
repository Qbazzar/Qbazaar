<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What finance staff are asked to review. Each one is a key in
 * lang/{locale}/admin.php `finance.review_requested`.
 */
enum FinanceReview: string
{
    case SETTLEMENT = 'settlement';
    case WITHDRAWAL = 'withdrawal';
    case DISPUTE = 'dispute';

    public function routeName(): string
    {
        return match ($this) {
            self::SETTLEMENT => 'admin.finance.settlements.index',
            self::WITHDRAWAL => 'admin.finance.withdrawals.index',
            self::DISPUTE => 'admin.finance.disputes.index',
        };
    }
}
