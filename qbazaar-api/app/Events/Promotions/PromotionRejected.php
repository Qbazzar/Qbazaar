<?php

declare(strict_types=1);

namespace App\Events\Promotions;

use App\Models\AdPromotion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An admin rejected the bank transfer for a pending promotion; it never started. Dispatched after the commit.
 */
class PromotionRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly AdPromotion $promotion) {}
}
