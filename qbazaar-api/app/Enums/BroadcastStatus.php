<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * queued → sending → sent, or failed when a delivery chunk gives up for
 * good (some recipients may then have missed the broadcast).
 */
enum BroadcastStatus: string
{
    case QUEUED = 'queued';
    case SENDING = 'sending';
    case SENT = 'sent';
    case FAILED = 'failed';

    public function label(): string
    {
        return (string) __('admin.broadcasts.statuses.' . $this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::QUEUED => 'neutral',
            self::SENDING => 'info',
            self::SENT => 'success',
            self::FAILED => 'danger',
        };
    }
}
