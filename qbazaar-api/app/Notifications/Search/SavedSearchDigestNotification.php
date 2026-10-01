<?php

declare(strict_types=1);

namespace App\Notifications\Search;

use App\Enums\Language;
use App\Enums\NotificationTopic;
use App\Models\User;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Notifications\Concerns\SendsFcmPush;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;

/**
 * "N more ads match your saved searches" — push only, because each match
 * already sits in the bell as its own `search.match` entry.
 */
class SavedSearchDigestNotification extends Notification
{
    use RespectsNotificationPreferences, SendsFcmPush;

    public function __construct(public readonly int $matches) {}

    protected function topic(): NotificationTopic
    {
        return NotificationTopic::SAVED_SEARCH_ALERTS;
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return $this->withoutMutedChannels($notifiable, [FcmChannel::class]) !== [] && $this->fcmEnabledFor($notifiable)
            ? [FcmChannel::class]
            : [];
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
            'category' => 'search.digest',
            'title' => (string) __('messages.notifications.saved_search_digest.title', [], $locale),
            'body' => trans_choice('messages.notifications.saved_search_digest.body', $this->matches, ['count' => $this->matches], $locale),
            'cta_url' => rtrim((string) config('qbazaar.web_url', config('app.url')), '/') . '/account/saved-searches',
            'matches' => $this->matches,
        ];
    }
}
