<?php

declare(strict_types=1);

namespace App\Notifications\Search;

use App\Models\Ad;
use App\Models\User;
use App\Notifications\Ads\AdAlertNotification;
use App\Services\Search\SavedSearchPushBatcher;
use NotificationChannels\Fcm\FcmChannel;

/**
 * "A new ad matches your saved search" — sent to a saved-search owner when a
 * freshly-published ad matches their stored filters.
 *
 * Delivered via the in-app bell (database) + push (FCM) only. We deliberately
 * skip email here: alerts can be frequent and a bell badge + push is the right
 * weight for "there's something new to look at". Pushes are batched per user
 * by {@see SavedSearchPushBatcher}; the bell gets every match.
 */
class SavedSearchMatchNotification extends AdAlertNotification
{
    public function __construct(
        Ad $ad,
        public readonly string $savedSearchName,
    ) {
        parent::__construct($ad);
    }

    /**
     * Laravel calls this once per send, when the notification is queued, so
     * the batcher counts each match once.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = parent::via($notifiable);

        if (! $notifiable instanceof User || ! in_array(FcmChannel::class, $channels, true)) {
            return $channels;
        }

        return app(SavedSearchPushBatcher::class)->pushNow($notifiable->id)
            ? $channels
            : array_values(array_diff($channels, [FcmChannel::class]));
    }

    protected function category(): string
    {
        return 'search.match';
    }

    protected function title(string $locale): string
    {
        return (string) __('messages.notifications.saved_search_match.title', [], $locale);
    }

    protected function body(string $locale): string
    {
        return (string) __('messages.notifications.saved_search_match.body', [
            'title' => $this->ad->title,
            'search' => $this->savedSearchName,
        ], $locale);
    }
}
