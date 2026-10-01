<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Delivery channels a user can mute per topic. The in-app inbox is not one of
 * them: it is the record of everything that happened on the account.
 */
enum NotificationPreferenceChannel: string
{
    case EMAIL = 'email';
    case PUSH = 'push';
}
