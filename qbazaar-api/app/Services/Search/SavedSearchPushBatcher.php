<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Jobs\Search\SendSavedSearchDigestJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps saved-search pushes to one per user per
 * `qbazaar.search.saved_search_check_interval_minutes`.
 *
 * The first match in a window is pushed straight away; later matches only
 * reach the bell and are counted, and {@see SendSavedSearchDigestJob} sends
 * one "N new ads" push when the window closes.
 */
class SavedSearchPushBatcher
{
    public function pushNow(string $userId): bool
    {
        $windowEndsAt = now()->addSeconds($this->windowSeconds())->getTimestamp();

        if (Cache::add($this->windowKey($userId), $windowEndsAt, $this->windowSeconds())) {
            return true;
        }

        $this->deferToDigest($userId);

        return false;
    }

    /**
     * Takes the matches counted so far. Decrementing by what was read keeps
     * a match counted while the digest is being sent for the next one.
     */
    public function takePending(string $userId): int
    {
        $pending = (int) Cache::get($this->pendingKey($userId), 0);

        if ($pending > 0) {
            Cache::decrement($this->pendingKey($userId), $pending);
        }

        return $pending;
    }

    /** The digest push opens a new window, so the next match is batched too. */
    public function openWindow(string $userId): void
    {
        $windowEndsAt = now()->addSeconds($this->windowSeconds())->getTimestamp();

        Cache::put($this->windowKey($userId), $windowEndsAt, $this->windowSeconds());
    }

    public function windowSeconds(): int
    {
        return max(1, (int) config('qbazaar.search.saved_search_check_interval_minutes')) * 60;
    }

    private function deferToDigest(string $userId): void
    {
        Cache::add($this->pendingKey($userId), 0, $this->windowSeconds() * 2);
        Cache::increment($this->pendingKey($userId));

        $windowEndsAt = Cache::get($this->windowKey($userId));
        $sendAt = is_int($windowEndsAt) ? Carbon::createFromTimestamp($windowEndsAt) : now()->addSeconds($this->windowSeconds());

        SendSavedSearchDigestJob::dispatch($userId)->delay($sendAt);
    }

    private function windowKey(string $userId): string
    {
        return "saved-search:push-window:{$userId}";
    }

    private function pendingKey(string $userId): string
    {
        return "saved-search:digest-pending:{$userId}";
    }
}
