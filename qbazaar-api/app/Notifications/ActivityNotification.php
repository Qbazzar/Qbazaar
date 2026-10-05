<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Language;
use App\Models\User;
use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Notifications\Concerns\SendsFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;

/**
 * A notice about something that happened to one of the user's records: it
 * lands in the inbox (and over Reverb through NotificationCreated), and goes
 * out by email and push unless the user muted the topic. The title and body
 * come from `{translationKey}.title` / `.body` in the recipient's language.
 */
abstract class ActivityNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable, RespectsNotificationPreferences, SendsFcmPush;

    abstract protected function category(): string;

    abstract protected function translationKey(): string;

    /**
     * @return array<string, string|int>
     */
    abstract protected function translationParams(string $locale): array;

    abstract protected function ctaPath(): string;

    abstract protected function icon(): string;

    /**
     * Ids the client needs to open the record, stored with the payload.
     *
     * @return array<string, string|null>
     */
    abstract protected function references(): array;

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return [];
        }

        $channels = ['mail', 'database'];

        if ($this->fcmEnabledFor($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $this->withoutMutedChannels($notifiable, $channels);
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = $this->localeOf($notifiable);

        return (new MailMessage)
            ->subject($this->title($locale))
            ->line($this->body($locale))
            ->action(__('messages.activity_notifications.action', [], $locale), $this->ctaUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->localeOf($notifiable);

        return [
            'category' => $this->category(),
            'title' => $this->title($locale),
            'body' => $this->body($locale),
            'cta_url' => $this->ctaUrl(),
            'icon' => $this->icon(),
            ...$this->references(),
        ];
    }

    private function title(string $locale): string
    {
        return (string) __($this->translationKey() . '.title', $this->translationParams($locale), $locale);
    }

    private function body(string $locale): string
    {
        return (string) __($this->translationKey() . '.body', $this->translationParams($locale), $locale);
    }

    private function ctaUrl(): string
    {
        return rtrim((string) config('qbazaar.web_url', config('app.url')), '/') . $this->ctaPath();
    }

    private function localeOf(mixed $notifiable): string
    {
        return $notifiable instanceof User && $notifiable->language instanceof Language
            ? $notifiable->language->value
            : (string) config('qbazaar.default_language', 'ar');
    }
}
