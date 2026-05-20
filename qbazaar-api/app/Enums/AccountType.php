<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountType: string
{
    case PRIVATE_INDIVIDUAL = 'private';
    case BUSINESS = 'business';
}
