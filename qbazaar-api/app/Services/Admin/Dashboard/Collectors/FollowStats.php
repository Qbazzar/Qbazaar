<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard\Collectors;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * The most followed active business accounts, read from users_directory_idx
 * (account_type, status, followers_count) the way the companies directory
 * reads it, so the list costs a short index range, not a table scan.
 */
final class FollowStats
{
    private const int LIMIT = 5;

    /**
     * Rows in the shape of the dashboard's recent-activity lists.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topBusinesses(): array
    {
        return User::query()
            ->select(['id', 'full_name', 'followers_count'])
            ->where('account_type', AccountType::BUSINESS->value)
            ->where('status', UserStatus::ACTIVE->value)
            ->where('followers_count', '>', 0)
            ->with('avatarMedia')
            ->orderByDesc('followers_count')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(static fn (User $user): array => [
                'title' => $user->full_name,
                'meta' => number_format($user->followers_count),
                'person' => ['id' => $user->getKey(), 'name' => (string) $user->full_name, 'avatar' => $user->avatarThumbUrl()],
                'badge' => ['key' => 'admin.home.followers'],
                'at' => null,
                'href' => route('admin.users.show', $user, false),
            ])
            ->values()
            ->all();
    }
}
