<?php

declare(strict_types=1);

namespace App\Notifications\Account;

use App\Enums\SanctionReason;
use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use App\Notifications\Concerns\ResolvesRecipientLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a user their account was suspended, and why (the preset reason only).
 * The suspension already signed them out and dropped their device tokens, so
 * email carries it; the inbox copy is there if the account is reactivated.
 */
class UserSuspendedNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable, ResolvesRecipientLocale;

    public function __construct(
        public readonly SanctionReason $reason,
    ) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = $this->recipientLocale($notifiable);

        return (new MailMessage)
            ->subject(__('messages.moderation.user_suspended.subject', [], $locale))
            ->greeting(__('messages.moderation.greeting', [], $locale))
            ->line(__('messages.moderation.user_suspended.line_intro', [], $locale))
            ->line(__('messages.moderation.line_reason', ['reason' => $this->reason->userLabel($locale)], $locale))
            ->action(__('messages.moderation.action_help', [], $locale), $this->helpUrl())
            ->line(__('messages.moderation.line_appeal', [], $locale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->recipientLocale($notifiable);

        return [
            'category' => 'account.suspended',
            'title' => __('messages.moderation.user_suspended.title', [], $locale),
            'body' => __('messages.moderation.user_suspended.body', ['reason' => $this->reason->userLabel($locale)], $locale),
            'cta_url' => $this->helpUrl(),
            'icon' => 'shield-alert',
            'reason' => $this->reason->value,
        ];
    }

    private function helpUrl(): string
    {
        return rtrim((string) config('qbazaar.web_url', config('app.url')), '/') . '/support';
    }
}
