<?php

declare(strict_types=1);

namespace App\Listeners\Notifications;

use App\Models\DeviceToken;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\SendReport;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Deletes device tokens that FCM reports as permanently dead.
 *
 * FcmChannel dispatches one NotificationFailed per failed SendReport with
 * `['report' => SendReport]` as the event data. Two failure classes mean the
 * token will never work again, so keeping the row only wastes future sends:
 *
 *  - messageWasSentToUnknownToken() — kreait NotFound, FCM "UNREGISTERED"
 *    (app uninstalled, push permission revoked, token rotated away);
 *  - messageTargetWasInvalid()      — malformed/invalid token
 *    (FCM INVALID_ARGUMENT).
 *
 * Anything else (quota, transient 5xx, auth hiccups) keeps the token.
 */
class PruneStaleDeviceTokens
{
    public function handle(NotificationFailed $event): void
    {
        if ($event->channel !== FcmChannel::class) {
            return;
        }

        $report = $event->data['report'] ?? null;

        if (! $report instanceof SendReport) {
            return;
        }

        if (! $report->messageWasSentToUnknownToken() && ! $report->messageTargetWasInvalid()) {
            return;
        }

        $target = $report->target();

        if ($target->type() !== MessageTarget::TOKEN) {
            return;
        }

        $deviceToken = DeviceToken::query()
            ->where('token', $target->value())
            ->first();

        if ($deviceToken === null) {
            return;
        }

        $deviceToken->delete();

        Log::info('Pruned stale FCM device token', [
            'device_token_id' => $deviceToken->id,
            'user_id' => $deviceToken->user_id,
            'platform' => $deviceToken->platform,
        ]);
    }
}
