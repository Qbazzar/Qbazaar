<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who caused a posting: the user themselves, a staff member acting in the
 * admin panel, or the platform (a job or an automatic rule).
 */
enum LedgerActorType: string
{
    case USER = 'user';
    case ADMIN = 'admin';
    case SYSTEM = 'system';
}
