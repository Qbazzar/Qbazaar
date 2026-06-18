<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Language;
use App\Models\User;
use App\Notifications\Channels\TwilioSmsChannel;
use App\Notifications\Channels\TwilioSmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Delivers a fresh OTP to a phone (and, in dev mode, also the user's email).
 *
 * Channel routing:
 *  - Twilio SMS — always attempted. In dev mode (no Twilio creds) the channel
 *    falls back to a Log::info entry so devs can grab the code locally.
 *  - Mail — only when a User model is present (i.e. a registered user
 *    resending the code from their account). For unknown / anonymous phones
 *    we just SMS.
 *
 * Localisation: the body is rendered against the user's preferred language
 * if available, otherwise the app default.
 */
class OtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
        $channels = [TwilioSmsChannel::class];

        if ($notifiable instanceof User && $notifiable->email !== '') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Phone-routing hook for the Twilio channel.
     */
    public function routeNotificationForTwilio(object $notifiable): string
    {
        return $this->phone;
    }

    public function toTwilio(object $notifiable): TwilioSmsMessage
    {
        return new TwilioSmsMessage(
            body: $this->renderBody($notifiable),
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->resolveLocale($notifiable);
        $minutes = (int) ceil($this->expiresInSeconds / 60);

        return (new MailMessage)
            ->subject(__('auth.otp.mail.subject', [], $locale))
            ->greeting(__('auth.otp.mail.greeting', [], $locale))
            ->line(__('auth.otp.mail.line_code', ['code' => $this->code], $locale))
            ->line(__('auth.otp.mail.line_expires', ['minutes' => $minutes], $locale))
            ->line(__('auth.otp.mail.line_ignore', [], $locale));
    }

    private function renderBody(object $notifiable): string
    {
        $locale = $this->resolveLocale($notifiable);
        $minutes = (int) ceil($this->expiresInSeconds / 60);

        return __('auth.otp.sms.body', [
            'code' => $this->code,
            'minutes' => $minutes,
        ], $locale);
    }

    private function resolveLocale(object $notifiable): string
    {
        if ($notifiable instanceof User) {
            return $notifiable->language instanceof Language
                ? $notifiable->language->value
                : (string) config('qbazaar.default_language', 'ar');
        }

        return (string) config('qbazaar.default_language', 'ar');
    }
}
