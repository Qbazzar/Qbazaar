<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageType: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case OFFER = 'offer';
    case SYSTEM = 'system';

    /** Written by a participant, so it is pushed and screened like chat text. */
    public function isUserWritten(): bool
    {
        return $this === self::TEXT || $this === self::IMAGE;
    }
}
