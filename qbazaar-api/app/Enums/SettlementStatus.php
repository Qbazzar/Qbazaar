<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A commission settlement:
 *
 *   pending ──► approved | rejected
 *
 * A bank transfer waits for an admin to match it against the bank
 * statement; netting from the wallet is approved the moment it is posted.
 */
enum SettlementStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function canTransitionTo(self $next): bool
    {
        return $this === self::PENDING && $next !== self::PENDING;
    }
}
