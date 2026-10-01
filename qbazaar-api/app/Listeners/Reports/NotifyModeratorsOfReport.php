<?php

declare(strict_types=1);

namespace App\Listeners\Reports;

use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Events\Reports\ReportCreated;
use App\Models\User;
use App\Notifications\Reports\ReportFiledNotification;
use App\Services\Admin\StaffDirectory;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Puts a new report in the bell of every active staff member who can act on
 * reports. Queued so filing a report never waits on the staff fan-out; a
 * report already handled by the time the job runs needs no alert.
 */
class NotifyModeratorsOfReport implements ShouldQueueAfterCommit
{
    public const HANDLER_PERMISSION = 'reports.action';

    private const CHUNK_SIZE = 100;

    public string $queue = 'low';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 60;

    public function __construct(
        private readonly StaffDirectory $staff,
    ) {}

    public function handle(ReportCreated $event): void
    {
        $report = $event->report;

        if ($report->status !== ReportStatus::PENDING) {
            return;
        }

        $handlerIds = $this->staff->idsWithPermission(self::HANDLER_PERMISSION);

        if ($handlerIds === []) {
            return;
        }

        $notification = new ReportFiledNotification($report);

        User::query()
            ->whereIn('id', $handlerIds)
            ->where('status', UserStatus::ACTIVE->value)
            ->chunkById(self::CHUNK_SIZE, function (Collection $handlers) use ($notification): void {
                Notification::send($handlers, $notification);
            });
    }
}
