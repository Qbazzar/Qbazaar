<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\Badgeable;

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
enum PromotionStatus: string implements Badgeable
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

    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::PENDING_PAYMENT => ['ar' => 'بانتظار الدفع', 'en' => 'Awaiting payment'],
            self::ACTIVE => ['ar' => 'نشط', 'en' => 'Active'],
            self::EXPIRED => ['ar' => 'منتهي', 'en' => 'Expired'],
            self::REJECTED => ['ar' => 'مرفوض', 'en' => 'Rejected'],
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'warning',
            self::ACTIVE => 'success',
            self::EXPIRED => 'neutral',
            self::REJECTED => 'danger',
        };
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
