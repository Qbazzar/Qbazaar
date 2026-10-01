<?php

declare(strict_types=1);

namespace App\Jobs\Search;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\Search\SavedSearchDigestNotification;
use App\Services\Search\SavedSearchPushBatcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one push for the saved-search matches a user got while their push
 * window was closed. Unique per user, so every batched match in a window
 * shares one delayed job.
 */
class SendSavedSearchDigestJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 30;

    public function __construct(public readonly string $userId)
    {
        $this->onQueue('low');
    }

    public function uniqueId(): string
    {
        return $this->userId;
    }

    /** Outlives the delay, so the lock is released by the run rather than by expiry. */
    public function uniqueFor(): int
    {
        return app(SavedSearchPushBatcher::class)->windowSeconds() * 2;
    }

    public function handle(SavedSearchPushBatcher $batcher): void
    {
        $user = User::query()->whereKey($this->userId)->where('status', UserStatus::ACTIVE->value)->first();
        $pending = $batcher->takePending($this->userId);

        if ($user === null || $pending === 0) {
            return;
        }

        $batcher->openWindow($this->userId);
        $user->notifyNow(new SavedSearchDigestNotification($pending));
    }
}
