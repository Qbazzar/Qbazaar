<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Data\Account\NotificationPreferences;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
        // Read-modify-write on one JSON column: the row lock stops two
        // concurrent partial updates from overwriting each other.
        return DB::transaction(function () use ($user, $email, $push): NotificationPreferences {
            $current = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail()->notification_preferences;
            $preferences = $current->with($email, $push);

            $user->forceFill(['notification_preferences' => $preferences])->save();

            return $preferences;
        });
    }
}
