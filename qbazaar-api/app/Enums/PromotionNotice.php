<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a seller is told about a paid promotion. Each case is a notification
 * category (`promotion.{value}`) and a key under `messages.promotion_notifications`.
 */
enum PromotionNotice: string
{
    case ACTIVATED = 'activated';
    case EXPIRED = 'expired';
    case REJECTED = 'rejected';

    public function category(): string
    {
        return 'promotion.' . $this->value;
    }
}
