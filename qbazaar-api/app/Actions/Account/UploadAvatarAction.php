<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Replaces the user's avatar in the `avatar` MediaLibrary collection.
 *
 * The collection is registered with `singleFile()` on the User model so the
 * package itself enforces the "one avatar at a time" rule. We still call
 * `clearMediaCollection('avatar')` explicitly first as defence in depth —
 * cheap when empty, and it guarantees we never end up with stale
 * conversions sitting on disk if the package's signing ever drifts.
 *
 * Conversions (`thumb` 200×200, `medium` 480×480) are registered on the
 * User model and run synchronously (`->nonQueued()`); the controller can
 * therefore return the conversion URLs in the same response.
 */
class UploadAvatarAction
{
    public function execute(User $user, UploadedFile $file): Media
    {
        $user->clearMediaCollection('avatar');

        return $user->addMedia($file)
            ->usingFileName($this->safeFilename($file))
            ->toMediaCollection('avatar');
    }

    /**
     * MediaLibrary already sanitises filenames; we additionally lower-case
     * and strip whitespace so the on-disk path is predictable across
     * platforms (case-sensitive S3 / case-insensitive local FS).
     */
    private function safeFilename(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'jpg';

        return 'avatar-' . time() . '.' . $extension;
    }
}
