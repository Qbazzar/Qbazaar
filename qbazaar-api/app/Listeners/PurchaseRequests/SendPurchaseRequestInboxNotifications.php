<?php

declare(strict_types=1);

namespace App\Listeners\PurchaseRequests;

use App\Events\PurchaseRequests\PurchaseRequestAccepted;
use App\Events\PurchaseRequests\PurchaseRequestCancelled;
use App\Events\PurchaseRequests\PurchaseRequestCreated;
use App\Events\PurchaseRequests\PurchaseRequestRejected;
use App\Events\PurchaseRequests\PurchaseRequestUpdated;
use App\Models\User;
use App\Notifications\PurchaseRequests\PurchaseRequestInboxNotification;

/**
 * Writes the inbox row for the side that did not act: every purchase request
 * event already carries that user, so the actor is excluded by construction.
 * A request the platform cancelled (the ad was sold, expired or removed)
 * goes to the buyer the same way. The notification itself is queued.
 */
class SendPurchaseRequestInboxNotifications
{
    public function handle(
        PurchaseRequestCreated|PurchaseRequestUpdated|PurchaseRequestAccepted|PurchaseRequestRejected|PurchaseRequestCancelled $event,
    ): void {
        User::query()->find($event->otherUserId)
            ?->notify(new PurchaseRequestInboxNotification($event->purchaseRequest, $event->broadcastAs()));
    }
}
