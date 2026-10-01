<?php

declare(strict_types=1);

namespace App\Listeners\Ads;

use App\Enums\UserStatus;
use App\Events\Ads\AdSubmittedForReview;
use App\Models\User;
use App\Notifications\Ads\AdPendingReviewNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Tells every active staff member who can approve ads that one is waiting,
 * in the panel bell and by email. Queued so the publish request never waits
 * on the staff lookup or the fan-out.
 *
 * Reviewers are found by permission, not role name, so a custom role that is
 * granted `ads.approve` is notified too.
 */
class NotifyAdminsOfPendingAd implements ShouldQueue
{
    public const REVIEW_PERMISSION = 'ads.approve';

    private const CHUNK_SIZE = 100;

    public string $queue = 'default';

    public function handle(AdSubmittedForReview $event): void
    {
        $notification = new AdPendingReviewNotification(
            $event->ad,
            flagged: ! $event->result->clean,
            flags: $event->result->flags,
        );

        $this->reviewers()->chunkById(self::CHUNK_SIZE, function (Collection $reviewers) use ($notification): void {
            Notification::send($reviewers, $notification);
        });
    }

    /**
     * whereHas rather than Spatie's `permission()` scope, which throws when
     * the permission has not been seeded yet.
     *
     * @return Builder<User>
     */
    private function reviewers(): Builder
    {
        $hasPermission = static fn (Builder $permissions) => $permissions->where('name', self::REVIEW_PERMISSION);

        return User::query()
            ->where('status', UserStatus::ACTIVE->value)
            ->where(static fn (Builder $query) => $query
                ->whereHas('roles.permissions', $hasPermission)
                ->orWhereHas('permissions', $hasPermission));
    }
}
