<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Notifications\Reports\ReportResolvedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Records a staff decision on a report. The reporter hears about it once,
 * when the report first reaches an outcome (actioned or dismissed); a
 * "reviewed" mark is internal and a later change of mind stays silent.
 */
class ResolveReportAction
{
    public function execute(Report $report, ReportStatus $status, User $staff, ?string $notes = null): Report
    {
        [$resolved, $firstOutcome] = DB::transaction(function () use ($report, $status, $staff, $notes): array {
            /** @var Report $locked */
            $locked = Report::query()->lockForUpdate()->findOrFail($report->id);
            $hadOutcome = $this->isOutcome($locked->status);

            $locked->forceFill([
                'status' => $status,
                'reviewed_at' => now(),
                'reviewed_by' => $staff->id,
                'admin_notes' => $notes ?? $locked->admin_notes,
            ])->save();

            return [$locked, ! $hadOutcome && $this->isOutcome($status)];
        });

        if ($firstOutcome && $resolved->reporter_id !== null) {
            DB::afterCommit(fn () => $resolved->reporter?->notify(new ReportResolvedNotification($resolved)));
        }

        return $resolved;
    }

    private function isOutcome(ReportStatus $status): bool
    {
        return $status === ReportStatus::ACTIONED || $status === ReportStatus::DISMISSED;
    }
}
