<?php

declare(strict_types=1);

namespace App\Notifications\Ads;

use App\Enums\Language;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Daily reminder fired ~24h before `expires_at`. Encourages a one-click
 * renewal so listings don't drop out of search.
 */
class AdExpiringSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Ad $ad) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return $notifiable instanceof User
            ? ['mail', 'database']
            : ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = $this->resolveLocale($notifiable);

        return (new MailMessage)
            ->subject(__('messages.ad_notifications.expiring_soon.subject', [], $locale))
            ->greeting(__('messages.ad_notifications.expiring_soon.greeting', [], $locale))
            ->line(__('messages.ad_notifications.expiring_soon.line_intro', [
                'title' => $this->ad->title,
                'expires_at' => $this->ad->expires_at?->toDayDateTimeString() ?? '',
            ], $locale))
            ->action(__('messages.ad_notifications.expiring_soon.action', [], $locale), $this->renewUrl())
            ->line(__('messages.ad_notifications.expiring_soon.line_outro', [
                'days' => (int) config('qbazaar.ads.lifetime_days', 30),
            ], $locale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->resolveLocale($notifiable);

        return [
            'category' => 'ad.expiring_soon',
            'title' => __('messages.notifications.ad_expiring_soon.title', [], $locale),
            'body' => __('messages.notifications.ad_expiring_soon.body', ['title' => $this->ad->title], $locale),
            'cta_url' => $this->renewUrl(),
            'icon' => 'clock',
            'ad_id' => $this->ad->id,
            'expires_at' => $this->ad->expires_at?->toIso8601String(),
        ];
    }

    private function renewUrl(): string
    {
        return rtrim((string) config('qbazaar.web_url', config('app.url')), '/') . '/account/ads/' . $this->ad->id;
    }

    private function resolveLocale(mixed $notifiable): string
    {
        if ($notifiable instanceof User) {
            return $notifiable->language instanceof Language
                ? $notifiable->language->value
                : (string) config('qbazaar.default_language', 'ar');
        }

        return (string) config('qbazaar.default_language', 'ar');
    }
}
