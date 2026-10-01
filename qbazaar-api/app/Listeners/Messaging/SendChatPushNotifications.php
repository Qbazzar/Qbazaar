<?php

declare(strict_types=1);

namespace App\Listeners\Messaging;

use App\Enums\MessageType;
use App\Events\Messaging\MessageSent;
use App\Events\Offers\OfferAccepted;
use App\Events\Offers\OfferCountered;
use App\Events\Offers\OfferCreated;
use App\Events\Offers\OfferExpired;
use App\Events\Offers\OfferRejected;
use App\Events\Offers\OfferWithdrawn;
use App\Models\User;
use App\Notifications\Messaging\NewMessagePushNotification;
use App\Notifications\Messaging\OfferPushNotification;
use App\Services\Messaging\RealtimePresence;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Pushes chat activity to a recipient who has no open app.
 *
 * Offer and system bubbles are skipped on MessageSent because the matching
 * offer event already produces a more specific push for the same action.
 * Every event targets the other participant, so the actor never receives
 * a push about their own action.
 */
class SendChatPushNotifications implements ShouldQueue
{
    public function __construct(private readonly RealtimePresence $presence) {}

    public function handle(
        MessageSent|OfferCreated|OfferCountered|OfferAccepted|OfferRejected|OfferWithdrawn|OfferExpired $event,
    ): void {
        if ($event instanceof MessageSent) {
            $this->pushNewMessage($event);

            return;
        }

        $recipient = User::query()->find($event->otherUserId);

        if ($recipient === null || $this->presence->isOnline($recipient->id)) {
            return;
        }

        $recipient->notify(new OfferPushNotification($event->offer, $event->broadcastAs()));
    }

    private function pushNewMessage(MessageSent $event): void
    {
        $message = $event->message;

        if ($message->type !== MessageType::TEXT || $message->sender_id === $event->recipient->id) {
            return;
        }

        if ($this->presence->isOnline($event->recipient->id)) {
            return;
        }

        $message->loadMissing('sender');

        $event->recipient->notify(new NewMessagePushNotification($message, $message->sender));
    }
}
