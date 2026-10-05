<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\Badgeable;

/**
 * A commission settlement:
 *
 *   pending ──► approved | rejected
 *
 * A bank transfer waits for an admin to match it against the bank
 * statement; netting from the wallet is approved the moment it is posted.
 */
enum SettlementStatus: string implements Badgeable
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function canTransitionTo(self $next): bool
    {
        return $this === self::PENDING && $next !== self::PENDING;
    }

    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::PENDING => ['ar' => 'بانتظار المراجعة', 'en' => 'Pending review'],
            self::APPROVED => ['ar' => 'مقبولة', 'en' => 'Approved'],
            self::REJECTED => ['ar' => 'مرفوضة', 'en' => 'Rejected'],
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
