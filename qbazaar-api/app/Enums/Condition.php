<?php

declare(strict_types=1);

namespace App\Enums;

enum Condition: string
{
    case NEW_ITEM = 'new';
    case LIKE_NEW = 'like_new';
    case GOOD = 'good';
    case FAIR = 'fair';
    case FOR_PARTS = 'for_parts';
}
