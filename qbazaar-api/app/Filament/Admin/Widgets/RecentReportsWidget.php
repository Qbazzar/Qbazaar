<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\ReportStatus;
use App\Models\Report;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Last 10 pending reports with one-click dismiss / mark-reviewed actions.
 *
 * Lives on the dashboard so moderators can triage without navigating into the
 * full ReportResource list. The actions write the same audit fields the
 * resource page would, so the two surfaces stay consistent.
 */
class RecentReportsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 6;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->heading(__('admin.widgets.recent_reports.heading'))
            ->paginated(false)
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.widgets.recent_reports.id'))
                    ->limit(8),

                TextColumn::make('target_type')
                    ->label(__('admin.widgets.recent_reports.target'))
                    ->badge(),

                TextColumn::make('category')
                    ->label(__('admin.widgets.recent_reports.category'))
                    ->badge()
                    ->color('warning'),

                TextColumn::make('reporter.full_name')
                    ->label(__('admin.widgets.recent_reports.reporter'))
                    ->limit(24),

                TextColumn::make('created_at')
                    ->label(__('admin.widgets.recent_reports.created'))
                    ->since(),
            ])
            ->recordActions([
                Action::make('dismiss')
                    ->label(__('admin.actions.dismiss'))
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(fn (Report $record) => $this->dismiss($record)),

                Action::make('reviewed')
                    ->label(__('admin.actions.mark_reviewed'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Report $record) => $this->markReviewed($record)),
            ]);
    }

    /**
     * @return Builder<Report>
     */
    private function getQuery(): Builder
    {
        return Report::query()
            ->with(['reporter:id,full_name'])
            ->where('status', ReportStatus::PENDING->value)
            ->latest()
            ->limit(10);
    }

    private function dismiss(Report $report): void
    {
        $report->forceFill([
            'status' => ReportStatus::DISMISSED,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ])->save();

        Notification::make()
            ->title(__('admin.actions.report_dismissed'))
            ->success()
            ->send();
    }

    private function markReviewed(Report $report): void
    {
        $report->forceFill([
            'status' => ReportStatus::REVIEWED,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ])->save();

        Notification::make()
            ->title(__('admin.actions.report_reviewed'))
            ->success()
            ->send();
    }
}
