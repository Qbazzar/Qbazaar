<?php

declare(strict_types=1);

namespace App\Listeners\Ads;

use App\Events\Ads\AdSubmittedForReview;
use App\Filament\Admin\Resources\AdResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Sends an admin-panel (database) notification to every reviewer whenever a
 * seller submits an ad for review, so the panel bell surfaces the queue.
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

        $ad = $event->ad;
        $flagged = ! $event->result->clean;

        // The notification text is rendered + stored at creation time (during
        // the SELLER's publish request, whose locale may be English). The admin
        // panel is Arabic, so render these labels in Arabic explicitly rather
        // than in the request locale.
        $locale = (string) config('app.admin_locale', 'ar');

        Notification::make()
            ->title(__('admin.ad_review.title', [], $locale))
            ->body(__('admin.ad_review.body', [
                'title' => $ad->title,
                'hint' => $flagged
                    ? __('admin.ad_review.flagged', [
                        'flags' => implode(', ', $event->result->flags),
                    ], $locale)
                    : '',
            ], $locale))
            ->icon($flagged ? 'heroicon-o-flag' : 'heroicon-o-inbox-arrow-down')
            ->color($flagged ? 'warning' : 'info')
            ->actions([
                Action::make('review')
                    ->label(__('admin.ad_review.action', [], $locale))
                    ->url(AdResource::getUrl('view', ['record' => $ad]))
                    ->markAsRead(),
            ])
            ->sendToDatabase($reviewers);
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
