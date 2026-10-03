<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A withdrawal:
 *
 *   pending ──► paid | rejected
 *
 * The money leaves the wallet when the withdrawal is requested; paying
 * sends it out of the bank, rejecting puts it back in the wallet.
 */
enum WithdrawalStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case REJECTED = 'rejected';

    public function canTransitionTo(self $next): bool
    {
        return $this === self::PENDING && $next !== self::PENDING;
    }
}
