<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\UserStatus;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read side of the follow graph. Lists are cursor-paginated newest first on
 * the (user, id) indexes and skip accounts that are not active.
 */
class FollowQueries
{
    /** @return CursorPaginator<int, Follow> */
    public function followersOf(User $user): CursorPaginator
    {
        return $this->page(
            Follow::query()->where('followed_id', $user->id),
            'follower',
        );
    }

    /** @return CursorPaginator<int, Follow> */
    public function followingOf(User $user): CursorPaginator
    {
        return $this->page(
            Follow::query()->where('follower_id', $user->id),
            'followed',
        );
    }

    /**
     * Which of the given users the viewer follows, in one query for a whole page.
     *
     * @param list<string> $userIds
     * @return array<string, true>
     */
    public function followedAmong(User $viewer, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return Follow::query()
            ->where('follower_id', $viewer->id)
            ->whereIn('followed_id', $userIds)
            ->pluck('followed_id')
            ->mapWithKeys(static fn (string $id): array => [$id => true])
            ->all();
    }

    public function isFollowing(?User $viewer, User $target): bool
    {
        if ($viewer === null || $viewer->id === $target->id) {
            return false;
        }

        return Follow::query()
            ->where('follower_id', $viewer->id)
            ->where('followed_id', $target->id)
            ->exists();
    }

    /**
     * @param Builder<Follow> $query
     * @param 'follower'|'followed' $counterpart
     * @return CursorPaginator<int, Follow>
     */
    private function page(Builder $query, string $counterpart): CursorPaginator
    {
        return $query
            ->whereHas($counterpart, static fn (Builder $user) => $user->where('status', UserStatus::ACTIVE->value))
            ->with($counterpart)
            ->orderByDesc('id')
            ->cursorPaginate((int) config('qbazaar.social.follows_per_page'));
    }
}
