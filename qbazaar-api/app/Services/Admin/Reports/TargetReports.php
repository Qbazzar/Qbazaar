<?php

declare(strict_types=1);

namespace App\Services\Admin\Reports;

use App\Enums\ReportStatus;
use App\Enums\ReportTarget;
use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;

/**
 * The newest reports against one ad, user, conversation or message, with the
 * totals, for the admin detail pages. Bounded and served by
 * reports_target_status_idx: one aggregate and one list query, plus two eager
 * loads when the reporters are shown, whatever the number of reports.
 */
final class TargetReports
{
    public const DEFAULT_LIMIT = 5;

    public function for(
        ReportTarget $type,
        string $targetId,
        int $limit = self::DEFAULT_LIMIT,
        ?string $exceptId = null,
        bool $withReporters = false,
    ): TargetReportSummary {
        $scope = fn (): Builder => Report::query()
            ->where('target_type', $type->value)
            ->where('target_id', $targetId)
            ->when($exceptId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptId));

        // Aliases avoid OPEN, a reserved word in MySQL and MariaDB.
        $totals = $scope()
            ->toBase()
            ->selectRaw('count(*) as total_count')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as open_count', [ReportStatus::PENDING->value])
            ->first();

        $latest = $scope()
            ->when($withReporters, fn (Builder $query): Builder => $query->with(['reporter:id,full_name,email', 'reporter.avatarMedia']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();

        return new TargetReportSummary(
            latest: $latest,
            total: (int) ($totals->total_count ?? 0),
            open: (int) ($totals->open_count ?? 0),
        );
    }
}
