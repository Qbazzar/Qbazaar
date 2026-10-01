<?php

declare(strict_types=1);

namespace App\Enums;

enum OfferParty: string
{
    case BUYER = 'buyer';
    case SELLER = 'seller';

    public function opposite(): self
    {
        return $this === self::BUYER ? self::SELLER : self::BUYER;
    }
}
