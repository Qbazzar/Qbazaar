<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of `follows` and of the users' follower/following counts.
 * A counter moves only when a row was really inserted or deleted, so
 * repeated or concurrent calls cannot drift the totals.
 */
class FollowGraph
{
    /** @return bool whether a new follow was created */
    public function link(User $follower, User $followed): bool
    {
        return DB::transaction(function () use ($follower, $followed): bool {
            $inserted = Follow::query()->insertOrIgnore([
                'id' => (new Follow)->newUniqueId(),
                'follower_id' => $follower->id,
                'followed_id' => $followed->id,
                'created_at' => now(),
            ]);

            if ($inserted === 1) {
                $this->adjustCounts($follower->id, $followed->id, 1);
            }

            return $inserted === 1;
        });
    }

    /** @return bool whether a follow was removed */
    public function unlink(User $follower, User $followed): bool
    {
        return DB::transaction(fn (): bool => $this->remove($follower->id, $followed->id));
    }

    /**
     * Drop the follow in both directions, used when one user blocks the other.
     * Expects to run inside the caller's transaction.
     */
    public function severBetween(User $one, User $other): void
    {
        $this->remove($one->id, $other->id);
        $this->remove($other->id, $one->id);
    }

    private function remove(string $followerId, string $followedId): bool
    {
        $deleted = Follow::query()
            ->where('follower_id', $followerId)
            ->where('followed_id', $followedId)
            ->delete();

        if ($deleted > 0) {
            $this->adjustCounts($followerId, $followedId, -1);
        }

        return $deleted > 0;
    }

    /**
     * Both rows are updated in id order so two users following each other at
     * the same moment lock them in the same order and cannot deadlock.
     * toBase() skips touching updated_at: a new follower is not a profile edit.
     */
    private function adjustCounts(string $followerId, string $followedId, int $delta): void
    {
        $updates = [
            $followerId => 'following_count',
            $followedId => 'followers_count',
        ];
        ksort($updates, SORT_STRING);

        foreach ($updates as $userId => $column) {
            $query = User::query()->toBase()->where('id', $userId);

            $delta > 0
                ? $query->increment($column, $delta)
                : $query->where($column, '>', 0)->decrement($column, -$delta);
        }
    }
}
