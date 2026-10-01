<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Data\Account\NotificationPreferences;
use App\Models\User;

/**
 * Changes the given email and push switches; topics left out keep their
 * current value, so the same action serves PUT (all topics) and PATCH.
 */
class UpdateNotificationPreferencesAction
{
    /**
     * @param array<string, bool> $email
     * @param array<string, bool> $push
     */
    public function execute(User $user, array $email, array $push = []): NotificationPreferences
    {
        $preferences = $user->notification_preferences->with($email, $push);

        $user->forceFill(['notification_preferences' => $preferences])->save();

        return $preferences;
    }
}
