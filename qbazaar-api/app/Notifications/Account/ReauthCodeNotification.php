<?php

declare(strict_types=1);

namespace App\Notifications\Account;

use App\Models\User;
use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails the one-time code that confirms a sensitive account change.
 * Security mail: never muted by notification preferences.
 */
class ReauthCodeNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $minutes,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->language->value : (string) config('qbazaar.default_language');

        return (new MailMessage)
            ->subject(__('messages.account_change.reauth.subject', [], $locale))
            ->line(__('messages.account_change.reauth.line_code', ['code' => $this->code], $locale))
            ->line(__('messages.account_change.reauth.line_expires', ['minutes' => $this->minutes], $locale))
            ->line(__('messages.account_change.reauth.line_ignore', [], $locale));
    }
}
