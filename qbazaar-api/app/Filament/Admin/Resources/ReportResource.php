<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Enums\ReportTarget;
use App\Filament\Admin\Resources\ReportResource\Pages;
use App\Models\Report;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Moderation queue.
 *
 * Workflow:
 *  - Default tab = PENDING. Other statuses live behind tabs the moderator
 *    can switch into when reviewing historical decisions.
 *  - Each row shows the report + a short reporter / target snapshot. Clicking
 *    View opens the read-only detail page with the full description.
 *  - Three actions per row: Dismiss / Action taken / Mark reviewed. "Action
 *    taken" demands free-form admin_notes; the others record reviewer +
 *    timestamp only.
 */
class ReportResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?int $navigationSort = 40;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.reports');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.report.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.report.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Report::query()->where('status', ReportStatus::PENDING->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        // Reports are not editable by hand in this sprint — see ViewReport
        // for the read-only infolist. The form is required by Resource but
        // unused on this surface.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn ($query) => $query->with(['reporter:id,full_name,email']))
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.fields.id'))
                    ->limit(8)
                    ->copyable(),

                TextColumn::make('target_type')
                    ->label(__('admin.fields.target_type'))
                    ->badge(),

                TextColumn::make('target_id')
                    ->label(__('admin.fields.target_id'))
                    ->limit(10)
                    ->copyable(),

                TextColumn::make('category')
                    ->label(__('admin.fields.type'))
                    ->badge()
                    ->color('warning'),

                TextColumn::make('reporter.full_name')
                    ->label(__('admin.fields.reporter'))
                    ->searchable(),

                TextColumn::make('status')
                    ->label(__('admin.fields.status'))
                    ->badge()
                    ->color(static fn (ReportStatus $state): string => match ($state) {
                        ReportStatus::PENDING => 'warning',
                        ReportStatus::ACTIONED => 'success',
                        ReportStatus::DISMISSED => 'gray',
                        ReportStatus::REVIEWED => 'info',
                    }),

                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(self::statusOptions())
                    ->default(ReportStatus::PENDING->value),

                SelectFilter::make('target_type')
                    ->label(__('admin.fields.target_type'))
                    ->options([
                        ReportTarget::AD->value => 'Ad',
                        ReportTarget::USER->value => 'User',
                        ReportTarget::CONVERSATION->value => 'Conversation',
                        ReportTarget::MESSAGE->value => 'Message',
                    ]),

                SelectFilter::make('category')
                    ->label(__('admin.fields.type'))
                    ->options(self::categoryOptions()),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('dismiss')
                    ->label(__('admin.actions.dismiss'))
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(static fn (Report $r): bool => $r->status === ReportStatus::PENDING)
                    ->requiresConfirmation()
                    ->action(static fn (Report $r) => self::transition($r, ReportStatus::DISMISSED, null, 'admin.actions.report_dismissed')),

                Action::make('action_taken')
                    ->label(__('admin.actions.mark_actioned'))
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(static fn (Report $r): bool => $r->status === ReportStatus::PENDING)
                    ->schema([
                        Textarea::make('admin_notes')
                            ->label(__('admin.fields.admin_notes'))
                            ->required()
                            ->rows(3),
                    ])
                    ->action(static fn (Report $r, array $data) => self::transition($r, ReportStatus::ACTIONED, (string) ($data['admin_notes'] ?? ''), 'admin.actions.report_actioned')),

                Action::make('mark_reviewed')
                    ->label(__('admin.actions.mark_reviewed'))
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(static fn (Report $r): bool => $r->status === ReportStatus::PENDING)
                    ->requiresConfirmation()
                    ->action(static fn (Report $r) => self::transition($r, ReportStatus::REVIEWED, null, 'admin.actions.report_reviewed')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_dismiss')
                        ->label(__('admin.actions.bulk_dismiss'))
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(static function (Collection $records): void {
                            /** @var Collection<int, Report> $records */
                            $records
                                ->filter(static fn (Report $r): bool => $r->status === ReportStatus::PENDING)
                                ->each(static fn (Report $r) => self::transition($r, ReportStatus::DISMISSED, null));

                            Notification::make()->title(__('admin.actions.report_dismissed'))->success()->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
            'view' => Pages\ViewReport::route('/{record}'),
        ];
    }

    /**
     * Persist a status transition + reviewer audit fields. Pulled into a
     * private helper so the bulk + per-row actions share one code path.
     */
    private static function transition(Report $report, ReportStatus $status, ?string $notes, ?string $toastKey = null): void
    {
        $report->forceFill([
            'status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
            'admin_notes' => $notes ?? $report->admin_notes,
        ])->save();

        if ($toastKey !== null) {
            Notification::make()->title(__($toastKey))->success()->send();
        }
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        return [
            ReportStatus::PENDING->value => 'Pending',
            ReportStatus::REVIEWED->value => 'Reviewed',
            ReportStatus::DISMISSED->value => 'Dismissed',
            ReportStatus::ACTIONED->value => 'Actioned',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function categoryOptions(): array
    {
        $options = [];
        foreach (ReportCategory::cases() as $case) {
            $options[$case->value] = (string) str($case->value)->replace('_', ' ')->title();
        }

        return $options;
    }
}
