<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a user-facing notification is about. Users mute a topic per channel
 * (email / push); account and security notices have no topic and always go out.
 */
enum NotificationTopic: string
{
    case USER_MESSAGES = 'user_messages';
    case OFFERS = 'offers';
    case LISTING_UPDATES = 'listing_updates';
    case SAVED_SEARCH_ALERTS = 'saved_search_alerts';
    case NEWSLETTERS = 'newsletters';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
