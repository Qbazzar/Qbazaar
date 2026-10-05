<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\Badgeable;

/**
 * A withdrawal:
 *
 *   pending ──► paid | rejected
 *
 * The money leaves the wallet when the withdrawal is requested; paying
 * sends it out of the bank, rejecting puts it back in the wallet.
 */
enum WithdrawalStatus: string implements Badgeable
{
    case PENDING = 'pending';
    case PAID = 'paid';
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
            self::PENDING => ['ar' => 'بانتظار التحويل', 'en' => 'Awaiting transfer'],
            self::PAID => ['ar' => 'محوّلة', 'en' => 'Paid'],
            self::REJECTED => ['ar' => 'مرفوضة', 'en' => 'Rejected'],
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PAID => 'success',
            self::REJECTED => 'danger',
        };
    }
}
