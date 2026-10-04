<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\Badgeable;

/**
 * Lifecycle states for a price offer attached to a conversation.
 *
 * PENDING is the only open state; every other case is terminal.
 *
 *   - ACCEPTED  → the responder agreed to the offered price.
 *   - REJECTED  → the responder declined.
 *   - WITHDRAWN → the proposer pulled the offer before a response.
 *   - EXPIRED   → closed by the platform: the expiry window passed, the ad
 *                 stopped accepting offers, or another offer was accepted.
 *   - COUNTERED → the responder answered with a counter-offer, which lives
 *                 on a new row pointing back through `parent_offer_id`.
 */
enum OfferStatus: string implements Badgeable
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';
    case EXPIRED = 'expired';
    case COUNTERED = 'countered';

    public function isTerminal(): bool
    {
        return $this !== self::PENDING;
    }

    public function canTransitionTo(self $next): bool
    {
        return $this === self::PENDING && $next !== self::PENDING;
    }

    /**
     * The timestamp column stamped when an offer enters this state.
     */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::ACCEPTED => 'accepted_at',
            self::REJECTED => 'rejected_at',
            self::WITHDRAWN => 'withdrawn_at',
            self::COUNTERED => 'countered_at',
            self::PENDING, self::EXPIRED => null,
        };
    }

    /**
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::PENDING => ['ar' => 'قيد الانتظار', 'en' => 'Pending'],
            self::ACCEPTED => ['ar' => 'مقبول', 'en' => 'Accepted'],
            self::REJECTED => ['ar' => 'مرفوض', 'en' => 'Rejected'],
            self::WITHDRAWN => ['ar' => 'مسحوب', 'en' => 'Withdrawn'],
            self::EXPIRED => ['ar' => 'منتهٍ', 'en' => 'Expired'],
            self::COUNTERED => ['ar' => 'عرض مضاد', 'en' => 'Countered'],
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::ACCEPTED => 'success',
            self::REJECTED => 'danger',
            self::WITHDRAWN, self::EXPIRED => 'neutral',
            self::COUNTERED => 'info',
        };
    }
}
