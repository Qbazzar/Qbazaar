<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mails the 6-digit sign-in / sign-up code. The address may not belong to an
 * account yet, so the language comes from the request that asked for it.
 */
class EmailSignInCodeNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresInSeconds,
        public readonly string $language,
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
        $minutes = (int) ceil($this->expiresInSeconds / 60);

        return (new MailMessage)
            ->subject(__('auth.email_code.mail.subject', ['code' => $this->code], $this->language))
            ->greeting(__('auth.email_code.mail.greeting', [], $this->language))
            ->line(__('auth.email_code.mail.line_code', ['code' => $this->code], $this->language))
            ->line(__('auth.email_code.mail.line_expires', ['minutes' => $minutes], $this->language))
            ->line(__('auth.email_code.mail.line_ignore', [], $this->language))
            ->salutation(__('auth.mail.salutation', [], $this->language));
    }
}
