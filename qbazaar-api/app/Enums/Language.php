<?php

declare(strict_types=1);

namespace App\Enums;

enum Language: string
{
    case ARABIC = 'ar';
    case ENGLISH = 'en';

    public function isRtl(): bool
    {
        return $this === self::ARABIC;
    }
}
