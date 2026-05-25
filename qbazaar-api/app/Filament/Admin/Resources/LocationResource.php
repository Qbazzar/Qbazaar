<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\LocationType;
use App\Filament\Admin\Resources\LocationResource\Pages;
use App\Models\Location;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Location taxonomy editor (cities → districts → areas).
 *
 * Shape matches CategoryResource except for the `lat` / `lng` pair instead
 * of custom_fields. Same cache-invalidation strategy on save.
 */
class LocationResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?int $navigationSort = 31;

    protected static string|UnitEnum|null $navigationGroup = 'Taxonomy';

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.locations');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.location.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.location.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            KeyValue::make('name')
                ->label(__('admin.fields.name'))
                ->keyLabel('Locale')
                ->valueLabel('Translation')
                ->required()
                ->default(['en' => '', 'ar' => '']),

            Select::make('parent_id')
                ->label(__('admin.fields.parent'))
                ->relationship('parent', 'slug')
                ->searchable()
                ->preload(),

            TextInput::make('slug')
                ->label(__('admin.fields.slug'))
                ->required()
                ->maxLength(64)
                ->alphaDash()
                ->unique(ignoreRecord: true),

            Select::make('type')
                ->label(__('admin.fields.type'))
                ->options([
                    LocationType::CITY->value => 'City',
                    LocationType::DISTRICT->value => 'District',
                    LocationType::AREA->value => 'Area',
                ])
                ->required(),

            TextInput::make('lat')
                ->label(__('admin.fields.lat'))
                ->numeric()
                ->step(0.000001),

            TextInput::make('lng')
                ->label(__('admin.fields.lng'))
                ->numeric()
                ->step(0.000001),

            TextInput::make('order')
                ->label(__('admin.fields.order'))
                ->numeric()
                ->default(0),
        ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('order')
            ->defaultSort('order')
            ->columns([
                TextColumn::make('name.en')->label('Name (EN)')->searchable(query: static fn ($q, $s) => $q->where('name->en', 'like', "%{$s}%")),
                TextColumn::make('name.ar')->label('Name (AR)')->searchable(query: static fn ($q, $s) => $q->where('name->ar', 'like', "%{$s}%")),
                TextColumn::make('parent.slug')->label(__('admin.fields.parent'))->placeholder('—'),
                TextColumn::make('slug')->label(__('admin.fields.slug'))->searchable(),
                TextColumn::make('type')->label(__('admin.fields.type'))->badge(),
                TextColumn::make('order')->label(__('admin.fields.order'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    LocationType::CITY->value => 'City',
                    LocationType::DISTRICT->value => 'District',
                    LocationType::AREA->value => 'Area',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->after(static fn () => self::flushCache()),
                DeleteAction::make()->after(static fn () => self::flushCache()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->after(static fn () => self::flushCache()),
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'view' => Pages\ViewLocation::route('/{record}'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }

    public static function flushCache(): void
    {
        Cache::forget('locations.qatar');
    }
}
