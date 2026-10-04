<?php

declare(strict_types=1);

namespace App\Notifications\Finance;

use App\Enums\FinanceReview;
use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to finance staff when a settlement, a withdrawal or a dispute is
 * waiting for them. It links to the queue, not to the user's details.
 */
class FinanceReviewRequestedNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable;

    public function __construct(
        public readonly FinanceReview $review,
        public readonly string $amount,
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
            ->subject($this->title($locale))
            ->line($this->body($locale))
            ->action(__('admin.finance.review_requested.action', [], $locale), $this->queueUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->adminLocale();

        return [
            'category' => 'finance.' . $this->review->value . '_review',
            'title' => $this->title($locale),
            'body' => $this->body($locale),
            'cta_url' => $this->queueUrl(),
            'icon' => 'wallet',
        ];
    }

    private function title(string $locale): string
    {
        return __("admin.finance.review_requested.{$this->review->value}.title", [], $locale);
    }

    private function body(string $locale): string
    {
        return __("admin.finance.review_requested.{$this->review->value}.body", ['amount' => $this->amount], $locale);
    }

    private function queueUrl(): string
    {
        return route($this->review->routeName());
    }

    private function adminLocale(): string
    {
        return (string) config('app.admin_locale', 'ar');
    }
}
