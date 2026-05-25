<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SavedSearchResource\Pages;
use App\Models\SavedSearch;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only audit view of per-user saved searches. Mostly here so the
 * support team can troubleshoot "where's my saved filter?" tickets.
 */
class SavedSearchResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = SavedSearch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmark;

    protected static ?int $navigationSort = 54;

    protected static string|UnitEnum|null $navigationGroup = 'Communications';

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.saved_searches');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.saved_search.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.saved_search.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn ($q) => $q->with(['user:id,full_name,email']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.fields.name'))
                    ->searchable(),

                TextColumn::make('user.full_name')
                    ->label(__('admin.fields.subject'))
                    ->searchable(),

                TextColumn::make('query_params')
                    ->label(__('admin.fields.query_params'))
                    ->formatStateUsing(static fn ($state): string => is_array($state)
                        ? (string) json_encode($state, JSON_UNESCAPED_UNICODE)
                        : (string) $state)
                    ->limit(60),

                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
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
            'index' => Pages\ListSavedSearches::route('/'),
            'view' => Pages\ViewSavedSearch::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
