<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

/**
 * Where a demo order ends up: the last state its journey reaches.
 */
enum OrderOutcome
{
    case CREATED;
    case AWAITING_HANDOVER;
    case COMPLETED;
    case CANCELLED;
    case DISPUTE_OPEN;
    case DISPUTE_UPHELD;
    case DISPUTE_DISMISSED;

    public function isDispute(): bool
    {
        return in_array($this, [self::DISPUTE_OPEN, self::DISPUTE_UPHELD, self::DISPUTE_DISMISSED], true);
    }
}
