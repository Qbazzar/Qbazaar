<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case DEACTIVATED = 'deactivated';
    case PENDING_DELETION = 'pending_deletion';

    /** Whether the user may sign in and use protected endpoints. */
    public function canLogin(): bool
    {
        return $this === self::ACTIVE;
    }
}
