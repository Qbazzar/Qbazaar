<?php

declare(strict_types=1);

namespace App\Enums;

enum OfferStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case COUNTERED = 'countered';
    case EXPIRED = 'expired';

    public function isOpen(): bool
    {
        return $this === self::PENDING;
    }
}
