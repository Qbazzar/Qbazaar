<?php

declare(strict_types=1);

namespace App\Notifications\Business;

use App\Enums\BusinessNotice;
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
 * Tells a user what staff decided about their business account: the
 * application was approved, rejected or needs more information, or the
 * verified badge was granted or revoked. Account notices have no topic, so
 * they cannot be muted. Sent from the notifications queue.
 */
class BusinessAccountNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable, SendsFcmPush;

    public function __construct(
        public readonly BusinessNotice $notice,
        public readonly ?string $reason = null,
        public readonly ?string $applicationId = null,
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
            ->action(__('business.notifications.action', [], $locale), $this->accountUrl());
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
            'cta_url' => $this->accountUrl(),
            'icon' => 'business',
            'record_id' => $this->applicationId,
        ];
    }

    private function title(string $locale): string
    {
        return (string) __("business.notifications.{$this->notice->value}.title", [], $locale);
    }

    private function body(string $locale): string
    {
        return trim((string) __("business.notifications.{$this->notice->value}.body", ['reason' => $this->reason ?? ''], $locale));
    }

    private function accountUrl(): string
    {
        return rtrim((string) config('qbazaar.web_url', config('app.url')), '/') . '/account/verification';
    }

    private function localeOf(mixed $notifiable): string
    {
        return $notifiable instanceof User && $notifiable->language instanceof Language
            ? $notifiable->language->value
            : (string) config('qbazaar.default_language', 'ar');
    }
}
