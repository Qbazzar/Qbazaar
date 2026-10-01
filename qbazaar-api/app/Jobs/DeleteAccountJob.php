<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\QueueName;
use App\Models\User;
use App\Services\Account\AccountEraser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Permanently deletes one account whose deletion grace period is over.
 *
 * It is dispatched twice for the same user (delayed at request time, and by
 * the daily sweep as a safety net), so it re-checks the user every time:
 * still pending deletion, and requested at least the grace period ago. The
 * second check stops a delayed job from an earlier, cancelled request from
 * deleting an account whose new request is still inside its grace window.
 */
class DeleteAccountJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [180, 900];

    // Shorter than the overlap lock below, which must outlive a run but expire
    // before the first retry.
    public int $timeout = 100;

    public function __construct(
        public readonly string $userId,
    ) {
        $this->onQueue(QueueName::LOW);
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        // A worker killed at its timeout never releases the lock, so it has to
        // expire before the first retry, or that retry is dropped as an overlap.
        return [(new WithoutOverlapping($this->userId))->dontRelease()->expireAfter(120)];
    }

    public function handle(AccountEraser $eraser): void
    {
        $user = User::query()->withTrashed()->find($this->userId);

        if ($user === null) {
            return;
        }

        if (! $user->isDueForDeletion()) {
            Log::info('DeleteAccountJob: skipped, the account is not due for deletion', [
                'user_id' => $this->userId,
                'status' => $user->status->value,
            ]);

            return;
        }

        $eraser->erase($user);

        Log::info('DeleteAccountJob: account permanently deleted', ['user_id' => $this->userId]);
    }
}
