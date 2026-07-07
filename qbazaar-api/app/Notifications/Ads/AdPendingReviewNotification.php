<?php

declare(strict_types=1);

namespace App\Notifications\Ads;

use App\Models\Ad;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Database notification fanned out to reviewers when a seller submits an ad for
 * review, so the /manage notifications bell surfaces the moderation queue.
 *
 * Database-only: reviewers see it in the panel, not their inbox. The stored
 * payload keys (`title`, `body`, `cta_url`) are read verbatim by the panel's
 * NotificationController.
 */
class AdPendingReviewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param list<string> $flags
     */
    public function __construct(
        public readonly Ad $ad,
        public readonly bool $flagged,
        public readonly array $flags,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = (string) config('app.admin_locale', 'ar');

        $hint = $this->flagged
            ? __('admin.ad_review.flagged', ['flags' => implode(', ', $this->flags)], $locale)
            : '';

        return [
            'category' => 'ad.pending_review',
            'title' => __('admin.ad_review.title', [], $locale),
            'body' => __('admin.ad_review.body', ['title' => $this->ad->title, 'hint' => $hint], $locale),
            'cta_url' => rtrim((string) config('app.url'), '/') . '/manage/ads/' . $this->ad->id,
            'icon' => $this->flagged ? 'flag' : 'inbox',
            'ad_id' => $this->ad->id,
        ];
    }
}
