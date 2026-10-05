<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What an order participant is told about. Each case is a notification
 * category (`order.{value}`) and a key under `messages.order_notifications`.
 */
enum OrderNotice: string
{
    case CREATED = 'created';
    case AWAITING_HANDOVER = 'awaiting_handover';
    case HANDED_OVER = 'handed_over';
    case CANCELLED = 'cancelled';
    case COMMISSION_DUE = 'commission_due';
    case DISPUTE_OPENED = 'dispute_opened';
    case DISPUTE_RESOLVED = 'dispute_resolved';

    public function category(): string
    {
        return 'order.' . $this->value;
    }

    public function icon(): string
    {
        return match ($this) {
            self::COMMISSION_DUE => 'wallet',
            self::DISPUTE_OPENED, self::DISPUTE_RESOLVED => 'shield-alert',
            self::CANCELLED => 'circle-x',
            self::HANDED_OVER => 'package-check',
            self::CREATED, self::AWAITING_HANDOVER => 'shopping-bag',
        };
    }
}
