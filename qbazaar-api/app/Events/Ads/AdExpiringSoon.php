<?php

declare(strict_types=1);

namespace App\Events\Ads;

use App\Models\Ad;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once per expiry by the daily expiry job when an ad enters the
 * admin-set warning window. Drives the "your ad is about to expire — renew
 * now" notification.
 */
class AdExpiringSoon
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Ad $ad) {}
}
