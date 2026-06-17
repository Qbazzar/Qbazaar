<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Language;
use App\Models\User;
use App\Notifications\Concerns\BuildsEmailVerificationUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Localised welcome mail sent on successful sign-up.
 *
 * Embeds a deep link to the email-verification API route so the user can
 * complete verification straight from the welcome message — avoiding the need
 * to ALSO send a separate verification email immediately after register.
 *
 * The signed URL is generated with the same recipe as
 * App\Notifications\EmailVerificationNotification so the API verify route
 * accepts both URLs interchangeably. We can't `extend` VerifyEmail here — this
 * is a different notification — but we deliberately reuse the URL contract to
 * avoid maintaining two signing schemes.
 */
class WelcomeNotification extends Notification implements ShouldQueue
{
    use BuildsEmailVerificationUrl;
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->resolveLocale($notifiable);
        $name = $notifiable instanceof User ? $notifiable->full_name : '';

        $message = (new MailMessage)
            ->subject(__('auth.welcome.mail.subject', [], $locale))
            ->greeting(__('auth.welcome.mail.greeting', ['name' => $name], $locale))
            ->line(__('auth.welcome.mail.line_intro', [], $locale));

        if ($notifiable instanceof User && ! $notifiable->hasVerifiedEmail()) {
            $message
                ->line(__('auth.welcome.mail.line_verify', [], $locale))
                ->action(__('auth.welcome.mail.action', [], $locale), $this->verificationUrl($notifiable));
        }

        return $message
            ->line(__('auth.welcome.mail.line_ignore', [], $locale))
            ->salutation(__('auth.mail.salutation', [], $locale));
    }

    /**
     * Reuses the shared verification-URL recipe so the same verify-email link
     * (pointing at the frontend page) is accepted from either notification.
     */
    private function verificationUrl(User $user): string
    {
        return $this->buildFrontendVerificationUrl($user);
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
