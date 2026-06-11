<?php

declare(strict_types=1);

namespace App\Notifications\Ads;

use App\Enums\Language;
use App\Models\Ad;
use App\Models\User;
use App\Notifications\Concerns\SendsFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Fired after an ad is moved to EXPIRED by the daily expiry job. CTA links to
 * the seller's dashboard where they can renew with a single click.
 */
class AdExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsFcmPush;

    public function __construct(public readonly Ad $ad) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = $notifiable instanceof User
            ? ['mail', 'database']
            : ['mail'];

        if ($this->fcmEnabledFor($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = $this->resolveLocale($notifiable);

        return (new MailMessage)
            ->subject(__('messages.ad_notifications.expired.subject', [], $locale))
            ->greeting(__('messages.ad_notifications.expired.greeting', [], $locale))
            ->line(__('messages.ad_notifications.expired.line_intro', ['title' => $this->ad->title], $locale))
            ->action(__('messages.ad_notifications.expired.action', [], $locale), $this->renewUrl())
            ->line(__('messages.ad_notifications.expired.line_outro', [], $locale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->resolveLocale($notifiable);

        return [
            'category' => 'ad.expired',
            'title' => __('messages.notifications.ad_expired.title', [], $locale),
            'body' => __('messages.notifications.ad_expired.body', ['title' => $this->ad->title], $locale),
            'cta_url' => $this->renewUrl(),
            'icon' => 'clock-alert',
            'ad_id' => $this->ad->id,
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
