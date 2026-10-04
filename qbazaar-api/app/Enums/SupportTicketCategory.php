<?php

declare(strict_types=1);

namespace App\Enums;

enum SupportTicketCategory: string
{
    case GENERAL = 'general';
    case BILLING = 'billing';
    case TECHNICAL = 'technical';
    case ABUSE = 'abuse';
    case FEEDBACK = 'feedback';
    case OTHER = 'other';

    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::GENERAL => ['ar' => 'عام', 'en' => 'General'],
            self::BILLING => ['ar' => 'الفوترة', 'en' => 'Billing'],
            self::TECHNICAL => ['ar' => 'تقني', 'en' => 'Technical'],
            self::ABUSE => ['ar' => 'إساءة', 'en' => 'Abuse'],
            self::FEEDBACK => ['ar' => 'ملاحظات', 'en' => 'Feedback'],
            self::OTHER => ['ar' => 'أخرى', 'en' => 'Other'],
        };
    }
}
