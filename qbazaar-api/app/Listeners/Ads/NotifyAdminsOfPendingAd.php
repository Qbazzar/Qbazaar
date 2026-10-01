<?php

declare(strict_types=1);

namespace App\Listeners\Ads;

use App\Enums\AdStatus;
use App\Enums\UserStatus;
use App\Events\Ads\AdModerated;
use App\Models\User;
use App\Notifications\Ads\AdPendingReviewNotification;
use App\Services\Admin\StaffDirectory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Tells every active staff member who can approve ads that one is waiting,
 * in the panel bell and by email, once the auto-moderation hints for the
 * submission are ready. Queued so the staff lookup and the fan-out never
 * run inside the moderation job.
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

    public function handle(AdModerated $event): void
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
