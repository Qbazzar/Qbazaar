<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Promotion lifecycle:
 *
 *   pending_payment ──► active ──► expired
 *          │
 *          └──► rejected
 *
 * A wallet purchase starts active; a bank transfer waits in
 * PENDING_PAYMENT until an admin confirms or rejects the transfer.
 */
enum PromotionStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case REJECTED = 'rejected';

    public function canTransitionTo(self $next): bool
    {
        return match ([$this, $next]) {
            [self::PENDING_PAYMENT, self::ACTIVE],
            [self::PENDING_PAYMENT, self::REJECTED],
            [self::ACTIVE, self::EXPIRED] => true,
            default => false,
        };
    }

    /**
     * An open promotion holds its (ad, type) slot: the ad cannot buy the
     * same type again until it expires or is rejected.
     */
    public function isOpen(): bool
    {
        return $this === self::PENDING_PAYMENT || $this === self::ACTIVE;
    }

    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::ACTIVE => 'activated_at',
            self::EXPIRED => 'expired_at',
            self::REJECTED => 'rejected_at',
            self::PENDING_PAYMENT => null,
        };
    }
}
