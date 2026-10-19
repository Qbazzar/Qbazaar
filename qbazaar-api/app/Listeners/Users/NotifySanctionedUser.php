<?php

declare(strict_types=1);

namespace App\Listeners\Users;

use App\Events\Ads\AdSuspended;
use App\Events\Users\UserSuspended;
use App\Models\User;
use App\Notifications\Account\UserSuspendedNotification;
use App\Notifications\Ads\AdSuspendedNotification;

/**
 * Tells the affected user about a staff sanction. The notifications are
 * queued, so this only picks the recipient: the ad's owner (loaded with the
 * ad on the bulk path, so no extra query there) or the suspended account.
 */
class NotifySanctionedUser
{
    public function handle(AdSuspended|UserSuspended $event): void
    {
        if ($event instanceof UserSuspended) {
            $event->user->notify(new UserSuspendedNotification($event->sanction->reason));

            return;
        }

        $owner = $event->ad->user;

        if (! $owner instanceof User) {
            return;
        }

        $owner->notify(new AdSuspendedNotification(
            (string) $event->ad->id,
            (string) $event->ad->title,
            $event->sanction->action,
            $event->sanction->reason,
        ));
    }
}
