<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Language;
use App\Models\User;
use App\Notifications\Channels\TwilioSmsChannel;
use App\Notifications\Channels\TwilioSmsMessage;
use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Delivers a phone-verification OTP by SMS only.
 *
 * The code proves possession of the phone number, so it must never travel
 * over any other channel: emailing it to the account that claims the number
 * would let anyone verify a phone they do not own.
 *
 * Without Twilio credentials the channel logs the code instead (dev mode).
 */
class OtpNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable;

    public function __construct(
        public readonly string $phone,
        public readonly string $code,
        public readonly int $expiresInSeconds,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [TwilioSmsChannel::class];
    }

    public function routeNotificationForTwilio(object $notifiable): string
    {
        return $this->phone;
    }

    public function toTwilio(object $notifiable): TwilioSmsMessage
    {
        return new TwilioSmsMessage(
            body: __('auth.otp.sms.body', [
                'code' => $this->code,
                'minutes' => (int) ceil($this->expiresInSeconds / 60),
            ], $this->resolveLocale($notifiable)),
        );
    }

    private function resolveLocale(object $notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->language instanceof Language) {
            return $notifiable->language->value;
        }

        return (string) config('qbazaar.default_language', 'ar');
    }
}
