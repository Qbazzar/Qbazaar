<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Daily safety net for account deletion: queues a DeleteAccountJob for every
 * account whose grace period is over, in case the delayed job dispatched at
 * request time was lost. Served by `users(status, deletion_requested_at)`.
 */
class SweepDueAccountDeletionsJob implements ShouldQueue
{
    use Queueable;

    private const int CHUNK = 200;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        User::query()
            ->withTrashed()
            ->where('status', UserStatus::PENDING_DELETION->value)
            ->where('deletion_requested_at', '<=', User::deletionCutoff())
            ->select('id')
            ->chunkById(self::CHUNK, function ($users): void {
                foreach ($users as $user) {
                    DeleteAccountJob::dispatch($user->id);
                }
            });
    }
}
