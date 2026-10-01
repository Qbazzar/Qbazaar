<?php

declare(strict_types=1);

namespace App\Notifications\Reports;

use App\Enums\Language;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\Concerns\SendsFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Tells the reporter how their report ended. It says only whether action
 * was taken, never what: the reported person's sanctions are not the
 * reporter's business.
 */
class ReportResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsFcmPush;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly Report $report)
    {
        $this->onQueue('low');
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = ['database'];

        if ($this->fcmEnabledFor($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $notifiable instanceof User && $notifiable->language instanceof Language
            ? $notifiable->language->value
            : (string) config('qbazaar.default_language', 'ar');

        $outcome = $this->report->status === ReportStatus::ACTIONED ? 'report_actioned' : 'report_dismissed';

        return [
            'category' => 'report.resolved',
            'title' => __("messages.notifications.{$outcome}.title", [], $locale),
            'body' => __("messages.notifications.{$outcome}.body", [], $locale),
            'icon' => 'flag',
            'report_id' => $this->report->id,
            'outcome' => $this->report->status->value,
        ];
    }
}
