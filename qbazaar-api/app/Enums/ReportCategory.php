<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reason buckets surfaced in the "Report this listing" sheet.
 *
 *  - SPAM / FRAUD / INAPPROPRIATE / OFFENSIVE are the four hot-buttons
 *    the FE renders as quick-pick chips.
 *  - DUPLICATE / WRONG_CATEGORY are seller-quality signals that feed the
 *    moderation queue's auto-actioning heuristics in Sprint 11.
 *  - OTHER is the catch-all that requires a non-empty description so admins
 *    have context to work with.
 *
 * Stable string values — once shipped, never rename.
 */
enum ReportCategory: string
{
    case SPAM = 'spam';
    case FRAUD = 'fraud';
    case INAPPROPRIATE = 'inappropriate';
    case OFFENSIVE = 'offensive';
    case DUPLICATE = 'duplicate';
    case WRONG_CATEGORY = 'wrong_category';
    case OTHER = 'other';

    /**
     * Bilingual user-facing label. Used by `ReportResource` so the FE can
     * render the picked category without round-tripping through a separate
     * translations endpoint.
     *
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::SPAM => ['ar' => 'إعلان مزعج', 'en' => 'Spam'],
            self::FRAUD => ['ar' => 'احتيال', 'en' => 'Fraud'],
            self::INAPPROPRIATE => ['ar' => 'محتوى غير لائق', 'en' => 'Inappropriate content'],
            self::OFFENSIVE => ['ar' => 'محتوى مسيء', 'en' => 'Offensive content'],
            self::DUPLICATE => ['ar' => 'إعلان مكرر', 'en' => 'Duplicate listing'],
            self::WRONG_CATEGORY => ['ar' => 'تصنيف خاطئ', 'en' => 'Wrong category'],
            self::OTHER => ['ar' => 'أخرى', 'en' => 'Other'],
        };
    }
}
