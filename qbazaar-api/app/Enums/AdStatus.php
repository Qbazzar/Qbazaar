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
}
