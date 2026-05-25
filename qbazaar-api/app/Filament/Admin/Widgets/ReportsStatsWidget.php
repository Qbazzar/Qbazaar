<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\ReportStatus;
use App\Models\Report;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Abuse-report queue card.
 *
 * The "pending" stat colour escalates to `danger` past a small threshold so
 * the dashboard surfaces a backlog without requiring the moderator to scan
 * the table. Threshold lives here (not in config) because it's a UI hint,
 * not a business rule.
 */
class ReportsStatsWidget extends StatsOverviewWidget
{
    /** Above this many pending reports, the card paints red to signal a backlog. */
    private const int PENDING_ALARM_THRESHOLD = 10;

    protected static ?int $sort = 3;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $pending = Report::query()
            ->where('status', ReportStatus::PENDING->value)
            ->count();

        $actionedThisWeek = Report::query()
            ->where('status', ReportStatus::ACTIONED->value)
            ->where('reviewed_at', '>=', now()->startOfWeek())
            ->count();

        $dismissedThisWeek = Report::query()
            ->where('status', ReportStatus::DISMISSED->value)
            ->where('reviewed_at', '>=', now()->startOfWeek())
            ->count();

        return [
            Stat::make(__('admin.widgets.reports.pending'), (string) $pending)
                ->description(__('admin.widgets.reports.awaiting_review'))
                ->color($pending > self::PENDING_ALARM_THRESHOLD ? 'danger' : ($pending > 0 ? 'warning' : 'success')),

            Stat::make(__('admin.widgets.reports.actioned_week'), (string) $actionedThisWeek)
                ->description(__('admin.widgets.reports.since_monday'))
                ->color('primary'),

            Stat::make(__('admin.widgets.reports.dismissed_week'), (string) $dismissedThisWeek)
                ->description(__('admin.widgets.reports.since_monday'))
                ->color('gray'),
        ];
    }
}
