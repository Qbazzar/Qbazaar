<?php

declare(strict_types=1);

namespace App\Listeners\Ads;

use App\Enums\AdStatus;
use App\Enums\UserStatus;
use App\Events\Ads\AdSubmittedForReview;
use App\Models\User;
use App\Notifications\Ads\AdPendingReviewNotification;
use App\Services\Admin\StaffDirectory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Tells every active staff member who can approve ads that one is waiting,
 * in the panel bell and by email. Queued so the publish request never waits
 * on the staff lookup or the fan-out.
 *
 * Reviewers are found by permission, not role name, so a custom role that is
 * granted `ads.approve` is notified too. An ad already reviewed by the time
 * the job runs needs no alert.
 */
class NotifyAdminsOfPendingAd implements ShouldQueue
{
    public const REVIEW_PERMISSION = 'ads.approve';

    private const CHUNK_SIZE = 100;

    public string $queue = 'default';

    public function __construct(
        private readonly StaffDirectory $staff,
    ) {}

    public function handle(AdSubmittedForReview $event): void
    {
        if ($event->ad->status !== AdStatus::PENDING) {
            return;
        }

        $reviewerIds = $this->staff->idsWithPermission(self::REVIEW_PERMISSION);

        if ($reviewerIds === []) {
            return;
        }

        $notification = new AdPendingReviewNotification(
            $event->ad,
            flagged: ! $event->result->clean,
            flags: $event->result->flags,
        );

        User::query()
            ->whereIn('id', $reviewerIds)
            ->where('status', UserStatus::ACTIVE->value)
            ->chunkById(self::CHUNK_SIZE, function (Collection $reviewers) use ($notification): void {
                Notification::send($reviewers, $notification);
            });
    }
}
