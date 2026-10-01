<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells whether a user currently holds a live realtime connection.
 *
 * Every signed-in client subscribes to its own `private-user.{id}` channel,
 * so an occupied channel means the user is already receiving events in an
 * open app and a push would only duplicate what they see. Any doubt (other
 * broadcaster, Reverb unreachable) counts as offline: a duplicate push is
 * cheaper than a missed message.
 */
class RealtimePresence
{
    public function isOnline(string $userId): bool
    {
        if (! (bool) config('qbazaar.messaging.push_skip_online_recipients', true)) {
            return false;
        }

        $broadcaster = Broadcast::connection();

        if (! $broadcaster instanceof PusherBroadcaster) {
            return false;
        }

        try {
            $info = $broadcaster->getPusher()->getChannelInfo('private-user.' . $userId);

            return (bool) ($info->occupied ?? false);
        } catch (Throwable $exception) {
            Log::warning('Realtime presence lookup failed', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
