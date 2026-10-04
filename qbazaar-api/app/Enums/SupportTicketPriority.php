<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\Badgeable;

enum SupportTicketPriority: string implements Badgeable
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';

    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::LOW => ['ar' => 'منخفضة', 'en' => 'Low'],
            self::NORMAL => ['ar' => 'عادية', 'en' => 'Normal'],
            self::HIGH => ['ar' => 'مرتفعة', 'en' => 'High'],
            self::URGENT => ['ar' => 'عاجلة', 'en' => 'Urgent'],
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::LOW => 'neutral',
            self::NORMAL => 'info',
            self::HIGH => 'warning',
            self::URGENT => 'danger',
        };
    }
}
