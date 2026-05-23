<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Language;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Localised email-verification mail.
 *
 * Extends Laravel's built-in VerifyEmail so we keep its signed-URL machinery
 * (`URL::temporarySignedRoute`) and override `toMail()` to render copy from
 * our own ar/en files and point the link at our API verification route.
 */
class EmailVerificationNotification extends VerifyEmail
{
    /**
     * @param object|User $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $locale = $this->resolveLocale($notifiable);
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject(__('auth.email_verification.mail.subject', [], $locale))
            ->greeting(__('auth.email_verification.mail.greeting', [], $locale))
            ->line(__('auth.email_verification.mail.line_intro', [], $locale))
            ->action(__('auth.email_verification.mail.action', [], $locale), $url)
            ->line(__('auth.email_verification.mail.line_ignore', [], $locale));
    }

    protected function verificationUrl($notifiable): string
    {
        if (static::$createUrlCallback !== null) {
            /** @var string $url */
            $url = call_user_func(static::$createUrlCallback, $notifiable);

            return $url;
        }

        if (! $notifiable instanceof User) {
            return '';
        }

        $minutes = (int) config('auth.verification.expire', 60);

        return URL::temporarySignedRoute(
            'api.v1.auth.verify-email',
            Carbon::now()->addMinutes($minutes),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->email),
            ],
        );
    }

    /**
     * @param object|User $notifiable
     */
    private function resolveLocale($notifiable): string
    {
        if ($notifiable instanceof User) {
            return $notifiable->language instanceof Language
                ? $notifiable->language->value
                : (string) config('qbazaar.default_language', 'ar');
        }

        return (string) config('qbazaar.default_language', 'ar');
    }
}
