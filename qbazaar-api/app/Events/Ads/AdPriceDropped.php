<?php

declare(strict_types=1);

namespace App\Events\Ads;

use App\Models\Ad;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A live ad got cheaper. Dispatched after the update commits.
 */
class AdPriceDropped
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Ad $ad,
        public readonly string $previousPrice,
        public readonly string $newPrice,
    ) {}
}
