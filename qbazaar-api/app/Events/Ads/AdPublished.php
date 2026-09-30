<?php

declare(strict_types=1);

namespace App\Events\Ads;

use App\Models\Ad;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a draft successfully clears auto-moderation and transitions to
 * ACTIVE. Listeners drive:
 *  - Approval notification to the owner (SendAdNotifications)
 *  - Saved-search alerts (NotifySavedSearchMatches)
 *
 * Not broadcast — this is an internal pub/sub event, not a real-time push.
 */
class AdPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Ad $ad) {}
}
