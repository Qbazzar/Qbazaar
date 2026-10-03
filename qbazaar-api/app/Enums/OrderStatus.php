<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Order lifecycle:
 *
 *   created ──► awaiting_handover ──► completed
 *      │               │    │
 *      │               │    └──► disputed ──► completed | cancelled
 *      └───────────────┴──► cancelled
 *
 * CREATED waits for checkout (address, delivery and payment method);
 * AWAITING_HANDOVER waits for the item and the money to change hands.
 */
enum OrderStatus: string
{
    case CREATED = 'created';
    case AWAITING_HANDOVER = 'awaiting_handover';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case DISPUTED = 'disputed';

    public function canTransitionTo(self $next): bool
    {
        return match ([$this, $next]) {
            [self::CREATED, self::AWAITING_HANDOVER],
            [self::CREATED, self::CANCELLED],
            [self::AWAITING_HANDOVER, self::COMPLETED],
            [self::AWAITING_HANDOVER, self::CANCELLED],
            [self::AWAITING_HANDOVER, self::DISPUTED],
            [self::DISPUTED, self::COMPLETED],
            [self::DISPUTED, self::CANCELLED] => true,
            default => false,
        };
    }

    /**
     * An open order holds the ad: no second order can be placed on it.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::CREATED, self::AWAITING_HANDOVER, self::DISPUTED], true);
    }

    /**
     * The timestamp column stamped when an order enters this state.
     */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::AWAITING_HANDOVER => 'awaiting_handover_at',
            self::COMPLETED => 'completed_at',
            self::CANCELLED => 'cancelled_at',
            self::DISPUTED => 'disputed_at',
            self::CREATED => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_values(array_map(
            fn (self $status): string => $status->value,
            array_filter(self::cases(), fn (self $status): bool => $status->isActive()),
        ));
    }
}
