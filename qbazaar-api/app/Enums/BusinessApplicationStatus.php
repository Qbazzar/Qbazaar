<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\Badgeable;

/**
 * A business application:
 *
 *   pending ──► approved | rejected | needs_info
 *   needs_info ──► pending (the applicant resubmits) | approved | rejected
 *
 * Pending and needs_info are "open": a user has at most one open
 * application, enforced by a unique index on `open_user_id`.
 */
enum BusinessApplicationStatus: string implements Badgeable
{
    case PENDING = 'pending';
    case NEEDS_INFO = 'needs_info';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function isOpen(): bool
    {
        return $this === self::PENDING || $this === self::NEEDS_INFO;
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PENDING => $next !== self::PENDING,
            self::NEEDS_INFO => $next !== self::NEEDS_INFO,
            self::APPROVED, self::REJECTED => false,
        };
    }

    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::PENDING => ['ar' => 'بانتظار المراجعة', 'en' => 'Pending review'],
            self::NEEDS_INFO => ['ar' => 'بانتظار معلومات إضافية', 'en' => 'More information requested'],
            self::APPROVED => ['ar' => 'مقبول', 'en' => 'Approved'],
            self::REJECTED => ['ar' => 'مرفوض', 'en' => 'Rejected'],
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::NEEDS_INFO => 'info',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
