<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Message;
use App\Models\SavedSearch;
use App\Models\User;

/**
 * Counters for the account dashboard and the My Ads tabs.
 *
 * Ads are counted with one grouped query (one row per status); every other
 * counter is a single COUNT.
 */
class GetAccountSummaryAction
{
    /**
     * @return array{
     *     my_ads: int,
     *     drafts: int,
     *     ads_by_status: array<string, int>,
     *     conversations: int,
     *     unread_messages: int,
     *     unread_notifications: int,
     *     favorites: int,
     *     saved_searches: int
     * }
     */
    public function execute(User $user): array
    {
        $adsByStatus = $this->adCountsByStatus($user);
        $drafts = $adsByStatus[AdStatus::DRAFT->value];

        return [
            // Every listing that has left the draft stage.
            'my_ads' => array_sum($adsByStatus) - $drafts,
            'drafts' => $drafts,
            'ads_by_status' => $adsByStatus,
            'conversations' => Conversation::query()->forUser($user)->count(),
            'unread_messages' => Message::query()->unreadFor($user)->count(),
            'unread_notifications' => $user->unreadNotifications()->count(),
            'favorites' => Favorite::query()->where('user_id', $user->id)->count(),
            'saved_searches' => SavedSearch::query()->where('user_id', $user->id)->count(),
        ];
    }

    /**
     * @return array<string, int> every AdStatus value, zero when the user has none
     */
    private function adCountsByStatus(User $user): array
    {
        $counts = Ad::query()
            ->forUser($user)
            ->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = [];
        foreach (AdStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $byStatus;
    }
}
