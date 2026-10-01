<?php

declare(strict_types=1);

namespace App\Notifications\Account;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the OLD address once the email has changed, so the real owner
 * notices a takeover. Only a masked form of the new address is shown.
 */
class EmailChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $maskedNewEmail,
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
        return (new MailMessage)
            ->subject(__('messages.account_change.email_changed.subject'))
            ->line(__('messages.account_change.email_changed.line_intro', ['email' => $this->maskedNewEmail]))
            ->line(__('messages.account_change.email_changed.line_not_you'));
    }
}
