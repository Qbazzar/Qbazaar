<?php

declare(strict_types=1);

namespace App\Notifications\Reports;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Panel bell entry for staff who handle reports. Bell only: reports can
 * arrive in bursts, and an email per report would flood the team.
 *
 * The stored payload keys (`title`, `body`, `cta_url`) are read verbatim by
 * the panel's NotificationController.
 */
class ReportFiledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Report $report)
    {
        $this->onQueue('low');
    }

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

        return [
            'category' => 'report.created',
            'title' => __('admin.report_filed.title', [], $locale),
            'body' => __('admin.report_filed.body', [
                'category' => $this->report->category->label()[$locale === 'en' ? 'en' : 'ar'],
                'target' => __('admin.report.target.' . $this->report->target_type->value, [], $locale),
            ], $locale),
            'cta_url' => rtrim((string) config('app.url'), '/') . '/admin/reports/' . $this->report->id,
            'icon' => 'flag',
            'report_id' => $this->report->id,
        ];
    }
}
