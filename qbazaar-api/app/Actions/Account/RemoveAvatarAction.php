<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;

/**
 * Removes the user's avatar. Deleting the media row also deletes the original
 * and its conversions from the disk, so nothing is left behind. The legacy
 * `avatar_url` column is cleared too, since the profile falls back to it.
 */
class RemoveAvatarAction
{
    public function execute(User $user): void
    {
        $user->clearMediaCollection('avatar');

        if ($user->avatar_url !== null) {
            $user->forceFill(['avatar_url' => null])->save();
        }
    }
}
