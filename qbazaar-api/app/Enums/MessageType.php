<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageType: string
{
    case TEXT = 'text';
    case OFFER = 'offer';
    case SYSTEM = 'system';
}
