<?php

declare(strict_types=1);

namespace App\Events\Promotions;

use App\Models\AdPromotion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The promotion ran its full duration and no longer lifts the ad. Dispatched after the commit.
 */
class PromotionExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly AdPromotion $promotion) {}
}
