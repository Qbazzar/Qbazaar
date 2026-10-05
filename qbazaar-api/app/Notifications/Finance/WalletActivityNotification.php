<?php

declare(strict_types=1);

namespace App\Notifications\Finance;

use App\Enums\FinanceNotice;
use App\Enums\Language;
use App\Models\User;
use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use App\Notifications\Concerns\SendsFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Tells a user what happened to their money: a settlement or withdrawal
 * was received, approved, paid or rejected. Money notices have no topic,
 * so they cannot be muted, like account and security notices.
 */
class WalletActivityNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable, SendsFcmPush;

    /**
     * @param array{amount: string, reason?: string|null, reference?: string|null} $params
     */
    public function __construct(
        public readonly FinanceNotice $notice,
        public readonly string $recordId,
        public readonly array $params,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = ['mail', 'database'];

        if ($this->fcmEnabledFor($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = $this->localeOf($notifiable);

        return (new MailMessage)
            ->subject($this->title($locale))
            ->line($this->body($locale))
            ->action(__('messages.finance_notifications.action', [], $locale), $this->walletUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->localeOf($notifiable);

        return [
            'category' => $this->notice->category(),
            'title' => $this->title($locale),
            'body' => $this->body($locale),
            'cta_url' => $this->walletUrl(),
            'icon' => 'wallet',
            'record_id' => $this->recordId,
        ];
    }

    private function title(string $locale): string
    {
        return __("messages.finance_notifications.{$this->notice->value}.title", [], $locale);
    }

    private function body(string $locale): string
    {
        return __("messages.finance_notifications.{$this->notice->value}.body", [
            'amount' => $this->params['amount'],
            'reason' => $this->params['reason'] ?? '',
            'reference' => $this->params['reference'] ?? '',
        ], $locale);
    }

    private function walletUrl(): string
    {
        return rtrim((string) config('qbazaar.web_url', config('app.url')), '/') . '/account/wallet';
    }

    private function localeOf(mixed $notifiable): string
    {
        return $notifiable instanceof User && $notifiable->language instanceof Language
            ? $notifiable->language->value
            : (string) config('qbazaar.default_language', 'ar');
    }
}
