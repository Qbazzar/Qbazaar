<?php

declare(strict_types=1);

namespace App\Notifications\Ads;

use App\Enums\Language;
use App\Enums\NotificationTopic;
use App\Models\Ad;
use App\Models\User;
use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Notifications\Concerns\SendsFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;

/**
 * "Something happened to an ad you care about" — sent to many users at
 * once (followers, favouriters, saved-search owners), so it is delivered
 * to the bell and to push, never by email. Push follows
 * the recipient's switch for the alert's topic; the bell always gets it.
 */
abstract class AdAlertNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable, RespectsNotificationPreferences, SendsFcmPush;

    public function __construct(public readonly Ad $ad) {}

    abstract protected function category(): string;

    abstract protected function title(string $locale): string;

    abstract protected function body(string $locale): string;

    /**
     * Alerts about ads the user watches (saved searches, followed sellers,
     * favourites) share one switch, so the preferences contract stays the same.
     */
    protected function topic(): NotificationTopic
    {
        return NotificationTopic::SAVED_SEARCH_ALERTS;
    }

    /**
     * Extra structured fields clients can use instead of parsing the text.
     *
     * @return array<string, mixed>
     */
    protected function details(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = $notifiable instanceof User ? ['database'] : [];

        // The preference check is free; fcmEnabledFor() may cost a query.
        if ($this->withoutMutedChannels($notifiable, [FcmChannel::class]) !== [] && $this->fcmEnabledFor($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $notifiable instanceof User && $notifiable->language instanceof Language
            ? $notifiable->language->value
            : (string) config('qbazaar.default_language', 'ar');

        return [
            'category' => $this->category(),
            'title' => $this->title($locale),
            'body' => $this->body($locale),
            'cta_url' => rtrim((string) config('qbazaar.web_url', config('app.url')), '/') . '/ads/' . $this->ad->id,
            'icon' => 'bell-ring',
            'ad_id' => $this->ad->id,
            ...$this->details(),
        ];
    }
}
