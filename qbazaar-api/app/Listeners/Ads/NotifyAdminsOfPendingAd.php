<?php

declare(strict_types=1);

namespace App\Listeners\Ads;

use App\Events\Ads\AdSubmittedForReview;
use App\Models\User;
use App\Notifications\Ads\AdPendingReviewNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Sends a database notification to every reviewer whenever a seller submits an
 * ad for review, so the /manage notifications bell surfaces the queue.
 *
 * Reviewers are users holding `super_admin` or `moderator` — the same roles
 * that gate the panel's approve/reject actions. `support` is excluded: it can
 * read the panel but does not action the moderation queue.
 */
class NotifyAdminsOfPendingAd
{
    public function handle(AdSubmittedForReview $event): void
    {
        $reviewers = $this->reviewers();

        if ($reviewers->isEmpty()) {
            return;
        }

        Notification::send(
            $reviewers,
            new AdPendingReviewNotification(
                $event->ad,
                flagged: ! $event->result->clean,
                flags: $event->result->flags,
            ),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function reviewers(): Collection
    {
        // whereHas (not the `role()` scope) so a missing role never throws
        // RoleDoesNotExist and 500s the publish request — it just yields none.
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['super_admin', 'moderator']))
            ->get();
    }
}
