<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Language;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

/**
 * Localised password-reset mail.
 *
 * Extends Laravel's built-in ResetPassword notification so we keep its token
 * signing and "expires in N minutes" semantics for free, while overriding
 * `toMail()` to:
 *  - render copy via our own ar/en locale files (no hard-coded English),
 *  - point the reset link at the qbazaar-web URL so the email lands on the
 *    frontend reset page (we ship `email` + `token` query params).
 */
class PasswordResetNotification extends ResetPassword
{
    /**
     * @param object|User $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $locale = $this->resolveLocale($notifiable);
        $url = $this->buildResetUrl($notifiable);
        $expiresIn = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject(__('auth.password_reset.mail.subject', [], $locale))
            ->greeting(__('auth.password_reset.mail.greeting', [], $locale))
            ->line(__('auth.password_reset.mail.line_intro', [], $locale))
            ->action(__('auth.password_reset.mail.action', [], $locale), $url)
            ->line(__('auth.password_reset.mail.line_expires', ['minutes' => $expiresIn], $locale))
            ->line(__('auth.password_reset.mail.line_ignore', [], $locale));
    }

    private function buildResetUrl(object $notifiable): string
    {
        if (static::$createUrlCallback !== null) {
            /** @var string $url */
            $url = call_user_func(static::$createUrlCallback, $notifiable, $this->token);

            return $url;
        }

        if (! $notifiable instanceof User) {
            return '';
        }

        $webBase = rtrim((string) config('app.frontend_url', config('app.url', '')), '/');
        $email = $notifiable->email;

        // Sign the API route so a frontend-driven reset still rides a token
        // that the API can re-verify. The frontend will POST email+token to
        // /api/v1/auth/reset-password.
        $apiUrl = URL::temporarySignedRoute(
            'api.v1.auth.password.reset.link',
            now()->addMinutes((int) config('auth.passwords.users.expire', 60)),
            [
                'token' => $this->token,
                'email' => $email,
            ],
        );

        // Hand the frontend both the API-signed link (so it can verify) and
        // the raw token/email for the form.
        $query = http_build_query([
            'token' => $this->token,
            'email' => $email,
            'verify' => $apiUrl,
        ]);

        return $webBase === ''
            ? $apiUrl
            : $webBase . '/reset-password?' . $query;
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
