<?php

declare(strict_types=1);

namespace App\Notifications\Account;

use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the NEW address: the change only happens when its owner opens
 * the link, which proves the address works and belongs to them.
 */
class ConfirmEmailChangeNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable;

    public function __construct(
        public readonly string $confirmUrl,
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
        return (new MailMessage)
            ->subject(__('messages.account_change.confirm_email.subject'))
            ->line(__('messages.account_change.confirm_email.line_intro'))
            ->action(__('messages.account_change.confirm_email.action'), $this->confirmUrl)
            ->line(__('messages.account_change.confirm_email.line_expires', ['minutes' => $this->minutes]))
            ->line(__('messages.account_change.confirm_email.line_ignore'));
    }
}
