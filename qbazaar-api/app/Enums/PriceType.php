<?php

declare(strict_types=1);

namespace App\Enums;

enum PriceType: string
{
    case FIXED = 'fixed';
    case NEGOTIABLE = 'negotiable';
    case FREE = 'free';
    case CONTACT_FOR_PRICE = 'contact';
}
