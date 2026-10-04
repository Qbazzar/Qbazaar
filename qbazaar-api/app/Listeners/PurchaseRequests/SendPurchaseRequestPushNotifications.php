<?php

declare(strict_types=1);

namespace App\Listeners\PurchaseRequests;

use App\Enums\QueueName;
use App\Events\PurchaseRequests\PurchaseRequestAccepted;
use App\Events\PurchaseRequests\PurchaseRequestCancelled;
use App\Events\PurchaseRequests\PurchaseRequestCreated;
use App\Events\PurchaseRequests\PurchaseRequestPaid;
use App\Events\PurchaseRequests\PurchaseRequestRejected;
use App\Events\PurchaseRequests\PurchaseRequestUpdated;
use App\Models\User;
use App\Notifications\PurchaseRequests\PurchaseRequestPushNotification;
use App\Services\Messaging\RealtimePresence;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Pushes a purchase request change to the other side when their app is
 * closed. The matching system bubble is not pushed by the chat listener, so
 * this is the only push for the action.
 */
class SendPurchaseRequestPushNotifications implements ShouldQueue
{
    public string $queue = QueueName::REALTIME->value;

    // A push that arrives minutes late is noise, so retries stop early.
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [1, 5, 15];

    public int $timeout = 30;

    public function __construct(private readonly RealtimePresence $presence) {}

    public function handle(
        PurchaseRequestCreated|PurchaseRequestUpdated|PurchaseRequestAccepted|PurchaseRequestRejected|PurchaseRequestCancelled|PurchaseRequestPaid $event,
    ): void {
        if ($this->presence->isOnline($event->otherUserId)) {
            return;
        }

        User::query()->find($event->otherUserId)
            ?->notify(new PurchaseRequestPushNotification($event->purchaseRequest, $event->broadcastAs()));
    }
}
