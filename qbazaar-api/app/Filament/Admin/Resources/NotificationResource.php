<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\NotificationResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Read-only audit view over the `notifications` table. Lets an admin verify
 * what landed in a given user's bell without poking the DB directly.
 *
 *  - No create/edit. Notifications are produced by application code; mutating
 *    them here would corrupt the audit trail.
 *  - `notifiable` is rendered as a User name when the morph resolves to
 *    one — otherwise we fall back to "(unknown)" so a stale morph type
 *    doesn't crash the page.
 */
class NotificationResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = DatabaseNotification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.navigation_groups.communications');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.notifications');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.notification.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.notification.plural');
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
                    TextEntry::make('type')
                        ->label(__('admin.fields.type'))
                        ->badge()
                        ->formatStateUsing(static fn (string $state): string => (string) class_basename($state)),

                    TextEntry::make('notifiable_id')
                        ->label(__('admin.fields.subject'))
                        ->formatStateUsing(static function (DatabaseNotification $record): string {
                            if ($record->notifiable_type === User::class || is_a($record->notifiable_type, User::class, true)) {
                                return User::query()->find($record->notifiable_id)?->full_name ?? $record->notifiable_id;
                            }

                            return $record->notifiable_id;
                        }),

                    TextEntry::make('data')
                        ->label(__('admin.fields.title'))
                        ->formatStateUsing(static fn ($state): string => (string) (is_array($state) ? ($state['title'] ?? '') : ''))
                        ->placeholder('—'),

                    TextEntry::make('data_body')
                        ->label(__('admin.fields.body'))
                        ->state(static fn (DatabaseNotification $record): string => is_array($record->data) ? (string) ($record->data['body'] ?? '') : '')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

            Section::make(__('admin.sections.audit'))
                ->columns(3)
                ->schema([
                    TextEntry::make('id')
                        ->label(__('admin.fields.id'))
                        ->copyable()
                        ->fontFamily(FontFamily::Mono),

                    IconEntry::make('read_at')
                        ->label(__('admin.fields.read_at'))
                        ->boolean(),

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
                TextColumn::make('type')
                    ->label(__('admin.fields.type'))
                    ->formatStateUsing(static fn (string $state): string => (string) class_basename($state))
                    ->searchable(),

                TextColumn::make('notifiable_id')
                    ->label(__('admin.fields.subject'))
                    ->formatStateUsing(static function (DatabaseNotification $record): string {
                        if ($record->notifiable_type === User::class || is_a($record->notifiable_type, User::class, true)) {
                            $user = User::query()->find($record->notifiable_id);

                            return $user?->full_name ?? $record->notifiable_id;
                        }

                        return $record->notifiable_id;
                    }),

                TextColumn::make('data')
                    ->label(__('admin.fields.title'))
                    ->formatStateUsing(static fn ($state): string => (string) (
                        is_array($state) ? ($state['title'] ?? '') : ''
                    ))
                    ->limit(50),

                IconColumn::make('read_at')
                    ->label(__('admin.fields.read_at'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check')
                    ->falseIcon('heroicon-o-clock'),

                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
            'view' => Pages\ViewNotification::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
