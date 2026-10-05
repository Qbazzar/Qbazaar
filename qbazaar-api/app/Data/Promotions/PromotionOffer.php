<?php

declare(strict_types=1);

namespace App\Data\Promotions;

use App\Enums\PromotionType;

/**
 * One entry of the promotion catalogue: what a type costs and how long it
 * runs, as the admin set it.
 */
final readonly class PromotionOffer
{
    public function __construct(
        public PromotionType $type,
        public string $price,
        public string $currency,
        public int $durationDays,
    ) {}
}
