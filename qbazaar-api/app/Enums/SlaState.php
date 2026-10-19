<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How an item in a staff queue stands against its response target.
 */
enum SlaState: string
{
    case ON_TIME = 'on_time';
    case DUE_SOON = 'due_soon';
    case OVERDUE = 'overdue';

    public function tone(): string
    {
        return match ($this) {
            self::ON_TIME => 'success',
            self::DUE_SOON => 'warning',
            self::OVERDUE => 'danger',
        };
    }

    public function label(): string
    {
        return (string) __('admin.moderation.sla.' . $this->value);
    }
}
