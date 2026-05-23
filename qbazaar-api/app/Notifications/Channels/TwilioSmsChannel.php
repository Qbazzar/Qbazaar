<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;
use Twilio\Rest\Client as TwilioClient;

/**
 * Bridges Laravel's Notification system to the Twilio SMS REST API.
 *
 * Why hand-rolled instead of a community channel package?
 *  - We only need the bare `messages->create` call, not the broader feature
 *    surface of laravel-notification-channels/twilio.
 *  - Avoids pulling in another top-level dependency for ~30 lines of glue.
 *
 * Notifications opt-in by:
 *  1. Listing `TwilioSmsChannel::class` in their `via()` method.
 *  2. Implementing a `toTwilio($notifiable): TwilioSmsMessage` method.
 *
 * "Dev mode" (TWILIO_SID empty): we skip the HTTP call entirely and just log
 * the message, so local devs can grab the body without provisioning Twilio.
 */
class TwilioSmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTwilio')) {
            return;
        }

        /** @var TwilioSmsMessage $message */
        $message = $notification->toTwilio($notifiable);

        $to = $this->resolveRecipient($notifiable, $notification);
        if ($to === null) {
            return;
        }

        $sid = (string) config('services.twilio.sid', '');
        $token = (string) config('services.twilio.token', '');
        $from = (string) config('services.twilio.from', '');

        if ($sid === '' || $token === '' || $from === '') {
            // Dev mode — don't call Twilio; surface enough info to debug locally.
            Log::info('twilio.sms.dev_mode', [
                'to' => $to,
                'body' => $message->body,
                'reason' => 'TWILIO_SID/TOKEN/FROM not configured',
            ]);

            return;
        }

        try {
            $client = new TwilioClient($sid, $token);
            $client->messages->create($to, [
                'from' => $from,
                'body' => $message->body,
            ]);
        } catch (Throwable $e) {
            // Don't crash the request — OTP delivery failures should be logged
            // and surfaced via monitoring, never to the user.
            Log::error('twilio.sms.failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveRecipient(object $notifiable, Notification $notification): ?string
    {
        if (method_exists($notification, 'routeNotificationForTwilio')) {
            $candidate = $notification->routeNotificationForTwilio($notifiable);
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $candidate = $notifiable->routeNotificationFor('twilio', $notification);
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
