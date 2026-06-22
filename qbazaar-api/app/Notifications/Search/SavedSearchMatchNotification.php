<?php

declare(strict_types=1);

namespace App\Notifications\Search;

use App\Enums\Language;
use App\Models\Ad;
use App\Models\User;
use App\Notifications\Concerns\SendsFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;

/**
 * "A new ad matches your saved search" — sent to a saved-search owner when a
 * freshly-published ad matches their stored filters.
 *
 * Delivered via the in-app bell (database) + push (FCM) only. We deliberately
 * skip email here: alerts can be frequent and a bell badge + push is the right
 * weight for "there's something new to look at".
 */
class SavedSearchMatchNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsFcmPush;

    public function __construct(
        public readonly Ad $ad,
        public readonly string $savedSearchName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = $notifiable instanceof User ? ['database'] : [];

        if ($this->fcmEnabledFor($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->resolveLocale($notifiable);

        return [
            'category' => 'search.match',
            'title' => (string) __('messages.notifications.saved_search_match.title', [], $locale),
            'body' => (string) __('messages.notifications.saved_search_match.body', [
                'title' => $this->ad->title,
                'search' => $this->savedSearchName,
            ], $locale),
            'cta_url' => $this->adUrl(),
            'icon' => 'bell-ring',
            'ad_id' => $this->ad->id,
        ];
    }

    private function adUrl(): string
    {
        return rtrim((string) config('qbazaar.web_url', config('app.url')), '/') . '/ads/' . $this->ad->id;
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
