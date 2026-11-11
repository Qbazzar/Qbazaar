<?php

declare(strict_types=1);

namespace App\Listeners\Ads;

use App\Events\Ads\AdApproved;
use App\Events\Ads\AdExpired;
use App\Events\Ads\AdExpiringSoon;
use App\Events\Ads\AdPublished;
use App\Events\Ads\AdRejected;
use App\Events\Ads\AdRenewed;
use App\Notifications\Ads\AdApprovedNotification;
use App\Notifications\Ads\AdExpiredNotification;
use App\Notifications\Ads\AdExpiringSoonNotification;
use App\Notifications\Ads\AdRejectedNotification;

/**
 * One-stop fan-out from ad-lifecycle events to seller notifications.
 *
 * Keeping the routing here (instead of one listener per event) means future
 * lifecycle hooks can be added by extending the switch — no provider edits
 * needed. The owning user is loaded once per event and short-circuits when
 * absent (a hard-deleted owner should never crash a queued listener).
 *
 * AdRenewed is intentionally silent for now — sellers click the renew button
 * themselves, so a "you renewed" mail is noise. Kept in the switch as a
 * placeholder for the eventual push-only confirmation.
 */
class SendAdNotifications
{
    public function handle(
        AdPublished|AdApproved|AdRejected|AdExpiringSoon|AdExpired|AdRenewed $event,
    ): void {
        $ad = $event->ad;
        $owner = $ad->relationLoaded('user') ? $ad->user : $ad->user()->first();

        if ($owner === null) {
            return;
        }

        match (true) {
            $event instanceof AdPublished => $owner->notify(new AdApprovedNotification($ad)),
            $event instanceof AdApproved => $owner->notify(new AdApprovedNotification($ad)),
            $event instanceof AdRejected => $owner->notify(new AdRejectedNotification($ad, $event->result)),
            $event instanceof AdExpiringSoon => $owner->notify(new AdExpiringSoonNotification($ad)),
            $event instanceof AdExpired => $owner->notify(new AdExpiredNotification($ad)),
            $event instanceof AdRenewed => null, // No notification at this time.
        };
    }
}
