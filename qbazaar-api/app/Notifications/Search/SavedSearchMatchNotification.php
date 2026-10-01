<?php

declare(strict_types=1);

namespace App\Notifications\Search;

use App\Models\Ad;
use App\Notifications\Ads\AdAlertNotification;

/**
 * "A new ad matches your saved search" — sent to a saved-search owner when a
 * freshly-published ad matches their stored filters.
 *
 * Delivered via the in-app bell (database) + push (FCM) only. We deliberately
 * skip email here: alerts can be frequent and a bell badge + push is the right
 * weight for "there's something new to look at".
 */
class SavedSearchMatchNotification extends AdAlertNotification
{
    public function __construct(
        Ad $ad,
        public readonly string $savedSearchName,
    ) {
        parent::__construct($ad);
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
