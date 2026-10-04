<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\Badgeable;

enum SupportTicketStatus: string implements Badgeable
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case WAITING_USER = 'waiting_user';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public function isTerminal(): bool
    {
        return $this === self::RESOLVED || $this === self::CLOSED;
    }

    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::OPEN => ['ar' => 'مفتوحة', 'en' => 'Open'],
            self::IN_PROGRESS => ['ar' => 'قيد المعالجة', 'en' => 'In progress'],
            self::WAITING_USER => ['ar' => 'بانتظار المستخدم', 'en' => 'Waiting for user'],
            self::RESOLVED => ['ar' => 'تم الحل', 'en' => 'Resolved'],
            self::CLOSED => ['ar' => 'مغلقة', 'en' => 'Closed'],
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::OPEN => 'warning',
            self::IN_PROGRESS => 'info',
            self::WAITING_USER => 'violet',
            self::RESOLVED => 'success',
            self::CLOSED => 'neutral',
        };
    }
}
