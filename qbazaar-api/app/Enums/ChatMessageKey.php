<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Translatable chat bubbles. The value is stored in `messages.message_key`
 * and is part of the public contract: clients may render it from their own
 * bundles with the stored `params`.
 */
enum ChatMessageKey: string
{
    case OFFER_MADE = 'offer.made';
    case OFFER_COUNTERED = 'offer.countered';
    case OFFER_ACCEPTED = 'offer.accepted';
    case OFFER_REJECTED = 'offer.rejected';
    case OFFER_WITHDRAWN = 'offer.withdrawn';
    case IMAGE = 'image';

    public function translationKey(): string
    {
        return 'chat.' . $this->value;
    }
}
