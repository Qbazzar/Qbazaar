<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ActivityResource\Pages;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Read-only Spatie Activitylog viewer.
 *
 * Indexed by `log_name` so a moderator can filter to just `ad` or `user`
 * activity for a target investigation. The `properties` JSON is rendered
 * inside the View page; the table only surfaces a short event summary so
 * scanning stays fast.
 */
class ActivityResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 60;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.activity');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.activity.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.activity.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('log_name')
                    ->label(__('admin.fields.log_name'))
                    ->badge()
                    ->searchable(),

                TextColumn::make('description')
                    ->label(__('admin.fields.description'))
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('subject_type')
                    ->label(__('admin.fields.subject'))
                    ->formatStateUsing(static fn (?string $state): string => $state !== null ? (string) class_basename($state) : '—'),

                TextColumn::make('subject_id')
                    ->label(__('admin.fields.id'))
                    ->limit(10)
                    ->toggleable(),

                TextColumn::make('causer_id')
                    ->label(__('admin.fields.causer'))
                    ->limit(10),

                TextColumn::make('event')
                    ->label(__('admin.fields.event'))
                    ->badge(),

                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label(__('admin.fields.log_name'))
                    ->options(static fn (): array => Activity::query()
                        ->whereNotNull('log_name')
                        ->distinct()
                        ->orderBy('log_name')
                        ->pluck('log_name', 'log_name')
                        ->all()),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            'view' => Pages\ViewActivity::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
