<?php

declare(strict_types=1);

namespace App\Events\Promotions;

use App\Models\AdPromotion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The promotion started: paid from the wallet, or its bank transfer was confirmed by an admin. Dispatched after the commit.
 */
class PromotionActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly AdPromotion $promotion) {}
}
