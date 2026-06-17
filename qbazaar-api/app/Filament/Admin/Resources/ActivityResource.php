<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ActivityResource\Pages;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

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

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.navigation_groups.moderation');
    }

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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->columns(2)
                ->schema([
                    TextEntry::make('log_name')
                        ->label(__('admin.fields.log_name'))
                        ->badge()
                        ->placeholder('—'),

                    TextEntry::make('event')
                        ->label(__('admin.fields.event'))
                        ->badge()
                        ->placeholder('—'),

                    TextEntry::make('description')
                        ->label(__('admin.fields.description'))
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

            Section::make(__('admin.sections.target'))
                ->columns(3)
                ->schema([
                    TextEntry::make('subject_type')
                        ->label(__('admin.fields.subject'))
                        ->formatStateUsing(static fn (?string $state): string => $state !== null ? (string) class_basename($state) : '—'),

                    TextEntry::make('subject_id')
                        ->label(__('admin.fields.target_id'))
                        ->copyable()
                        ->fontFamily(FontFamily::Mono)
                        ->placeholder('—'),

                    TextEntry::make('causer_id')
                        ->label(__('admin.fields.causer'))
                        ->copyable()
                        ->fontFamily(FontFamily::Mono)
                        ->placeholder('—'),
                ]),

            Section::make(__('admin.sections.meta'))
                ->schema([
                    TextEntry::make('properties')
                        ->label(__('admin.fields.properties'))
                        ->formatStateUsing(static fn ($state): string => (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                        ->fontFamily(FontFamily::Mono)
                        ->placeholder('—')
                        ->columnSpanFull(),

                    TextEntry::make('created_at')
                        ->label(__('admin.fields.created_at'))
                        ->dateTime()
                        ->since(),
                ]),
        ]);
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
