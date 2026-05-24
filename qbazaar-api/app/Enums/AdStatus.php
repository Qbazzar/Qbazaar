<?php

declare(strict_types=1);

namespace App\Enums;

enum AdStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case REJECTED = 'rejected';
    case SOLD = 'sold';
    case EXPIRED = 'expired';
    case BLOCKED = 'blocked';

    /**
     * Whether this status is allowed to transition into $next.
     * Centralises the lifecycle rules used by AdPublisher / AdRenew /
     * AdMarkSold / moderation actions.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ([$this, $next]) {
            [self::DRAFT, self::PENDING], [self::DRAFT, self::ACTIVE] => true,
            [self::PENDING, self::ACTIVE], [self::PENDING, self::REJECTED] => true,
            [self::ACTIVE, self::SOLD],
            [self::ACTIVE, self::EXPIRED],
            [self::ACTIVE, self::BLOCKED] => true,
            [self::EXPIRED, self::ACTIVE] => true,   // renew
            [self::REJECTED, self::PENDING] => true, // edit & resubmit
            default => false,
        };
    }

    /** Ads in these statuses are visible to the public. */
    public function isPubliclyVisible(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Bilingual labels for the seller-facing status badge.
     *
     * Keys mirror the supported_languages config so the frontend can index
     * directly by the active locale without a translation lookup.
     *
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::DRAFT => ['ar' => 'مسودة', 'en' => 'Draft'],
            self::PENDING => ['ar' => 'قيد المراجعة', 'en' => 'Pending review'],
            self::ACTIVE => ['ar' => 'نشط', 'en' => 'Active'],
            self::SOLD => ['ar' => 'مُباع', 'en' => 'Sold'],
            self::EXPIRED => ['ar' => 'منتهي الصلاحية', 'en' => 'Expired'],
            self::REJECTED => ['ar' => 'مرفوض', 'en' => 'Rejected'],
            self::BLOCKED => ['ar' => 'محظور', 'en' => 'Blocked'],
        };
    }
}
