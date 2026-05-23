<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Hard-deletes a user 30 days after they requested it.
 *
 * Idempotency / safety:
 *  - We re-load the user inside the job and re-check
 *    `status === PENDING_DELETION` before destroying anything. If the user
 *    has logged back in (which flips them to ACTIVE) we treat the cancel
 *    as authoritative and exit without side effects.
 *  - We force-delete (bypass soft-delete) per GDPR-style retention: the
 *    deletion request period is the grace window, after that the row is
 *    truly gone. A tombstone in `activity_log` records that the deletion
 *    happened.
 *  - Storage paths owned by the user (avatars, queued exports) are cleared
 *    so we don't leave orphan blobs behind.
 */
class DeleteAccountJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $userId,
    ) {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        /** @var User|null $user */
        $user = User::query()->withTrashed()->find($this->userId);

        if ($user === null) {
            // Already deleted or never existed — nothing to do.
            return;
        }

        if ($user->status !== UserStatus::PENDING_DELETION) {
            // User cancelled by logging back in. Drop the job silently.
            Log::info('DeleteAccountJob: skipping — user is no longer pending deletion', [
                'user_id' => $this->userId,
                'status' => $user->status->value,
            ]);

            return;
        }

        DB::transaction(function () use ($user): void {
            // Clear media first so MediaLibrary's row deletes the underlying
            // disk file via its own observer. Force-deleting the user
            // afterwards would orphan the files on disk.
            $user->clearMediaCollection('avatar');

            // Wipe stored data-export blobs for this user — the signed URLs
            // become useless after the row is gone, but the JSON files
            // themselves should not linger.
            foreach (Storage::disk('local')->files('exports') as $path) {
                if (str_contains($path, $user->id . '-')) {
                    Storage::disk('local')->delete($path);
                }
            }

            $user->forceDelete();
        });

        Log::info('DeleteAccountJob: user permanently deleted', [
            'user_id' => $this->userId,
        ]);
    }
}
