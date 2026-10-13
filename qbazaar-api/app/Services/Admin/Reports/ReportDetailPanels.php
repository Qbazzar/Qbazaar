<?php

declare(strict_types=1);

namespace App\Services\Admin\Reports;

use App\Enums\ReportTarget;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use App\Services\Admin\EntityTimeline;
use App\Services\Admin\TimelineEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The data behind the admin report page: the reported record as a preview,
 * the other reports against it and the report's own history. Every part is
 * bounded and eager loaded, and read inside rescue() so a failing widget shows
 * its error state instead of failing the page.
 */
final class ReportDetailPanels
{
    public const OTHER_REPORTS_LIMIT = 5;

    public const TIMELINE_LIMIT = 8;

    /** Cap on the reported message text shown in the target section. */
    public const MESSAGE_PREVIEW_LENGTH = 600;

    private const MESSAGE_COLUMNS = ['id', 'conversation_id', 'sender_id', 'body', 'created_at'];

    public function __construct(
        private readonly TargetReports $reports,
        private readonly EntityTimeline $timeline,
    ) {}

    /**
     * target: the reported record, null once it is deleted, false when it failed to load.
     *
     * @return array{
     *     report: Report,
     *     target: Model|false|null,
     *     otherReports: TargetReportSummary|null,
     *     timeline: Collection<int, TimelineEntry>|null,
     *     canReadMessage: bool,
     * }
     */
    public function forShow(Report $report, User $viewer): array
    {
        $report->load(['reporter.avatarMedia', 'reviewer.avatarMedia']);
        // A reported private message is read only by those who can act on the report: they need it to decide.
        $canReadMessage = $viewer->can('reports.action');

        return [
            'report' => $report,
            'target' => rescue(fn (): ?Model => $this->targetOf($report, $canReadMessage), false),
            'canReadMessage' => $canReadMessage,
            'otherReports' => rescue(fn (): TargetReportSummary => $this->reports->for(
                $report->target_type,
                $report->target_id,
                limit: self::OTHER_REPORTS_LIMIT,
                exceptId: $report->id,
                withReporters: true,
            ), null),
            'timeline' => rescue(fn (): Collection => $this->timelineOf($report), null),
        ];
    }

    private function targetOf(Report $report, bool $canReadMessage): ?Model
    {
        return match ($report->target_type) {
            ReportTarget::AD => Ad::withTrashed()
                ->with(['primaryImage', 'category', 'user.avatarMedia'])
                ->find($report->target_id),
            ReportTarget::USER => User::withTrashed()->with('avatarMedia')->find($report->target_id),
            ReportTarget::CONVERSATION => Conversation::query()
                ->with(['ad:id,title', 'buyer.avatarMedia', 'seller.avatarMedia'])
                ->find($report->target_id),
            ReportTarget::MESSAGE => $canReadMessage
                ? Message::query()->with('sender.avatarMedia')->find($report->target_id, self::MESSAGE_COLUMNS)
                // Without the right to read it only the link back to the conversation is needed, never the text.
                : Message::query()->find($report->target_id, ['id', 'conversation_id', 'created_at']),
        };
    }

    /**
     * Staff decisions come from the audit log; the filing itself closes the list
     * once the log is short enough to reach back to it.
     *
     * @return Collection<int, TimelineEntry>
     */
    private function timelineOf(Report $report): Collection
    {
        $entries = $this->timeline->forSubject($report, self::TIMELINE_LIMIT);

        if ($entries->count() < self::TIMELINE_LIMIT) {
            $entries->push(new TimelineEntry(
                title: (string) __('admin.report.filed'),
                actor: $report->reporter !== null
                    ? ($report->reporter->full_name ?: $report->reporter->email)
                    : (string) __('admin.report.automatic_reporter'),
                at: CarbonImmutable::instance($report->created_at),
            ));
        }

        return $entries;
    }
}
