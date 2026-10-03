<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * "Buy Now" request lifecycle:
 *
 *   pending ──► accepted ──► paid
 *      │
 *      ├──► rejected   (the seller declined)
 *      └──► cancelled  (the buyer withdrew it, or the platform closed it:
 *                       another deal on the ad, or the ad left the market)
 *
 * PENDING is the only state the buyer can edit or the seller can answer.
 * PAID follows the handover of the order the acceptance created.
 */
enum PurchaseRequestStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case PAID = 'paid';

    public function canTransitionTo(self $next): bool
    {
        return match ([$this, $next]) {
            [self::PENDING, self::ACCEPTED],
            [self::PENDING, self::REJECTED],
            [self::PENDING, self::CANCELLED],
            [self::ACCEPTED, self::PAID] => true,
            default => false,
        };
    }

    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::ACCEPTED => 'accepted_at',
            self::REJECTED => 'rejected_at',
            self::CANCELLED => 'cancelled_at',
            self::PAID => 'paid_at',
            self::PENDING => null,
        };
    }
}
