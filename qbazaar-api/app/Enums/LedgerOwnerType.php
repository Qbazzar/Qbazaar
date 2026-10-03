<?php

declare(strict_types=1);

namespace App\Enums;

enum LedgerOwnerType: string
{
    case PLATFORM = 'platform';
    case USER = 'user';
}
