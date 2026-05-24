<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\Favorite;
use App\Models\User;

/**
 * Returns the at-a-glance counters used by the account dashboard.
 *
 * Most underlying tables (ads, conversations, notifications, favorites)
 * land in later sprints. To keep the wire contract stable from day one,
 * the schema is fixed and we return 0 for any field whose table doesn't
 * exist yet. Each placeholder carries a TODO comment so the Sprint owner
 * can plug the real query in without breaking clients.
 */
class GetAccountSummaryAction
{
    /**
     * @return array{
     *     my_ads: int,
     *     drafts: int,
     *     conversations: int,
     *     unread_notifications: int,
     *     favorites: int
     * }
     */
    public function execute(User $user): array
    {
        return [
            'my_ads' => 0,             // TODO Sprint 5: $user->ads()->whereNot('status', 'draft')->count()
            'drafts' => 0,             // TODO Sprint 5: $user->ads()->where('status', 'draft')->count()
            'conversations' => 0,      // TODO Sprint 8: count conversations the user participates in
            'unread_notifications' => 0, // TODO Sprint 10: count unread DatabaseNotifications
            'favorites' => Favorite::query()->where('user_id', $user->id)->count(),
        ];
    }
}
