<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\UserStatus;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sends an ad alert to one chunk of candidate users.
 *
 * Skips the seller, inactive accounts and anyone on either side of a block
 * with the seller, all in the one user query. Each recipient is claimed
 * once per `$scope` in the cache first, so a retried job or a repeated
 * event never alerts the same user twice.
 */
class AdAudienceNotifier
{
    /**
     * @param list<string> $userIds
     * @return int recipients notified
     */
    public function notify(Ad $ad, array $userIds, Notification $notification, string $scope): int
    {
        return $this->notifyEach($ad, array_fill_keys($userIds, $notification), $scope);
    }

    /**
     * @param array<string, Notification> $notificationsByUserId
     * @return int recipients notified
     */
    public function notifyEach(Ad $ad, array $notificationsByUserId, string $scope): int
    {
        if ($notificationsByUserId === []) {
            return 0;
        }

        $recipients = $this->eligibleUsers($ad, array_map('strval', array_keys($notificationsByUserId)))
            ->filter(fn (User $user): bool => $this->claim($scope, $user->id));

        foreach ($recipients as $user) {
            $user->notify($notificationsByUserId[$user->id]);
        }

        return $recipients->count();
    }

    /**
     * @param list<string> $userIds
     * @return Collection<int, User>
     */
    private function eligibleUsers(Ad $ad, array $userIds): Collection
    {
        $sellerId = $ad->user_id;

        return User::query()
            ->whereKey($userIds)
            ->whereKeyNot($sellerId)
            ->where('status', UserStatus::ACTIVE->value)
            ->whereNotIn('id', DB::table('user_blocks')->where('blocker_id', $sellerId)->select('blocked_id'))
            ->whereNotIn('id', DB::table('user_blocks')->where('blocked_id', $sellerId)->select('blocker_id'))
            ->with('deviceTokens:id,user_id')
            ->get();
    }

    private function claim(string $scope, string $userId): bool
    {
        return Cache::add(
            "ad-alert:{$scope}:{$userId}",
            true,
            now()->addHours((int) config('qbazaar.notifications.alert_dedupe_hours')),
        );
    }
}
