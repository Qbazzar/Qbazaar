<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Enums\ReportTarget;
use App\Events\Reports\ReportCreated;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\ReportTargetResolver;

/**
 * Business rules for submitting an abuse report.
 *
 *  1. The target row must exist (REPORT_INVALID_TARGET / 422). Without
 *     this guard a malicious client could fill the admin queue with
 *     dangling pointers.
 *  2. A user cannot report themselves (REPORT_SELF_FORBIDDEN / 422).
 *  3. A reporter may not file a second report against the same target
 *     within `qbazaar.reports.duplicate_window_days` (default 7 days).
 *     Throws REPORT_DUPLICATE / 429. Window is configurable so the rate
 *     can be tightened in response to abuse-of-reports.
 *
 * The DB write + event dispatch are deliberately NOT wrapped in a
 * transaction: the duplicate guard is an advisory check (a true race
 * between two concurrent reports is benign — one of them just becomes
 * the "duplicate" the admin sees) and the event fires post-insert so
 * downstream listeners always see a persisted row.
 */
class MakeReportAction
{
    public function __construct(
        private readonly ReportTargetResolver $resolver,
    ) {}

    public function execute(
        User $reporter,
        ReportTarget $targetType,
        string $targetId,
        ReportCategory $category,
        ?string $description,
    ): Report {
        // Cannot report yourself. Checked BEFORE the existence query to
        // avoid leaking whether your own ID is in the users table (it
        // always is, but the principle keeps callers tidy).
        if ($targetType === ReportTarget::USER && $targetId === $reporter->id) {
            throw new DomainException(ErrorCode::REPORT_SELF_FORBIDDEN);
        }

        if (! $this->resolver->exists($targetType, $targetId)) {
            throw new DomainException(ErrorCode::REPORT_INVALID_TARGET);
        }

        $windowDays = (int) config('qbazaar.reports.duplicate_window_days', 7);

        $hasRecent = Report::query()
            ->recentDuplicate($reporter->id, $targetType, $targetId, $windowDays)
            ->exists();

        if ($hasRecent) {
            throw new DomainException(ErrorCode::REPORT_DUPLICATE);
        }

        /** @var Report $report */
        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'target_type' => $targetType->value,
            'target_id' => $targetId,
            'category' => $category->value,
            'description' => $description,
            'status' => ReportStatus::PENDING->value,
        ]);

        ReportCreated::dispatch($report);

        return $report;
    }
}
