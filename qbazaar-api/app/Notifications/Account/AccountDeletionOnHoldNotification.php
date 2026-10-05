<?php

declare(strict_types=1);

namespace App\Notifications\Account;

use App\Enums\Language;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a user whose grace period is over that their account was not erased
 * because they still owe commission, have an order in progress, hold money
 * in the wallet or have a withdrawal or settlement waiting for review. Mail
 * is the channel that reaches them: their sessions and push tokens were
 * dropped when they asked for the deletion.
 */
class AccountDeletionOnHoldNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable;

    /**
     * @param array<string, mixed> $details the guard's refusal details (amount for a debt)
     */
    public function __construct(
        public readonly ErrorCode $reason,
        public readonly array $details = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        $hasEmail = $notifiable instanceof User && $notifiable->email !== null && $notifiable->email !== '';

        return $hasEmail ? ['database', 'mail'] : ['database'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = $this->resolveLocale($notifiable);

        return (new MailMessage)
            ->subject(__('messages.notifications.account_deletion_on_hold.title', [], $locale))
            ->line($this->body($locale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->resolveLocale($notifiable);

        return [
            'category' => 'account.deletion_on_hold',
            'title' => __('messages.notifications.account_deletion_on_hold.title', [], $locale),
            'body' => $this->body($locale),
            'cta_url' => null,
            'icon' => 'alert',
            'reason' => $this->reason->value,
        ];
    }

    private function body(string $locale): string
    {
        $amount = ['amount' => (string) ($this->details['amount'] ?? '')];

        return match ($this->reason) {
            ErrorCode::ACCOUNT_DEBT_OUTSTANDING => __('messages.notifications.account_deletion_on_hold.body_debt', $amount, $locale),
            ErrorCode::ACCOUNT_WALLET_NOT_EMPTY => __('messages.notifications.account_deletion_on_hold.body_wallet', $amount, $locale),
            ErrorCode::ACCOUNT_PAYOUT_PENDING => __('messages.notifications.account_deletion_on_hold.body_payout', [], $locale),
            default => __('messages.notifications.account_deletion_on_hold.body_open_order', [], $locale),
        };
    }

    private function resolveLocale(mixed $notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->language instanceof Language) {
            return $notifiable->language->value;
        }

        return (string) config('qbazaar.default_language', 'ar');
    }
}
