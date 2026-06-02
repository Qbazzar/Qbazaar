<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\User;

/**
 * Returns the at-a-glance counters used by the account dashboard.
 *
 * Ads are counted with a single grouped query (one row per status) so the
 * "my ads" / "drafts" split costs one round-trip rather than two; the
 * remaining counters are a single COUNT each — five numbers, four queries,
 * no N+1.
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
        $adCountsByStatus = Ad::query()
            ->forUser($user)
            ->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $drafts = (int) ($adCountsByStatus[AdStatus::DRAFT->value] ?? 0);
        $totalAds = (int) $adCountsByStatus->sum();

        return [
            // "My ads" is every listing that has left the draft stage —
            // active, pending, sold and expired all count as published work.
            'my_ads' => $totalAds - $drafts,
            'drafts' => $drafts,
            'conversations' => Conversation::query()->forUser($user)->count(),
            'unread_notifications' => $user->unreadNotifications()->count(),
            'favorites' => Favorite::query()->where('user_id', $user->id)->count(),
        ];
    }
}
