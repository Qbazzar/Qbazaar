<?php

declare(strict_types=1);

namespace App\Notifications\Ads;

use App\Models\Ad;
use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every reviewer when a seller submits an ad for review: a panel
 * bell entry plus an email, both written in the admin locale.
 *
 * The stored payload keys (`title`, `body`, `cta_url`) are read verbatim by
 * the panel's NotificationController.
 */
class AdPendingReviewNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable;

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
        return ['database', 'mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = $this->adminLocale();

        return (new MailMessage)
            ->subject(__('admin.ad_review.title', [], $locale))
            ->line($this->body($locale))
            ->action(__('admin.ad_review.action', [], $locale), $this->reviewUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->adminLocale();

        return [
            'category' => 'ad.pending_review',
            'title' => __('admin.ad_review.title', [], $locale),
            'body' => $this->body($locale),
            'cta_url' => $this->reviewUrl(),
            'icon' => $this->flagged ? 'flag' : 'inbox',
            'ad_id' => $this->ad->id,
        ];
    }

    private function body(string $locale): string
    {
        $hint = $this->flagged
            ? __('admin.ad_review.flagged', ['flags' => implode(', ', $this->flags)], $locale)
            : '';

        return __('admin.ad_review.body', ['title' => $this->ad->title, 'hint' => $hint], $locale);
    }

    private function reviewUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/admin/ads/' . $this->ad->id;
    }

    private function adminLocale(): string
    {
        return (string) config('app.admin_locale', 'ar');
    }
}
