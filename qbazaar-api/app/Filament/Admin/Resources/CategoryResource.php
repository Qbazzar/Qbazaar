<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CategoryResource\Pages;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Throwable;
use UnitEnum;

/**
 * Category taxonomy editor.
 *
 *  - `name` and `description` are stored as JSON `{ar, en}` — the matching
 *    public API resource preserves this shape, so the editor uses KeyValue
 *    inputs keyed by locale. The two top locales are seeded by default; an
 *    admin can add a third by hand if a new locale is ever launched.
 *  - The `after()` save hook flushes the related cache keys so the public
 *    feed picks up the change without a deploy. We don't try to be clever
 *    about which keys to wipe — busting the whole taxonomy cache for a
 *    handful of admin saves is cheap.
 */
class CategoryResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 30;

    protected static string|UnitEnum|null $navigationGroup = 'Taxonomy';

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.categories');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.category.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.category.plural');
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

            KeyValue::make('description')
                ->label(__('admin.fields.description'))
                ->keyLabel('Locale')
                ->valueLabel('Translation')
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

            TextInput::make('icon')
                ->label(__('admin.fields.icon'))
                ->helperText(__('admin.helpers.lucide_icon'))
                ->maxLength(64),

            TextInput::make('order')
                ->label(__('admin.fields.order'))
                ->numeric()
                ->default(0),

            Toggle::make('is_active')
                ->label(__('admin.fields.is_active'))
                ->default(true),

            KeyValue::make('custom_fields')
                ->label('Custom fields')
                ->keyLabel('Field key')
                ->valueLabel('Definition (JSON)'),

            KeyValue::make('custom_filters')
                ->label('Custom filters')
                ->keyLabel('Filter key')
                ->valueLabel('Definition (JSON)'),
        ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('order')
            ->defaultSort('order')
            ->columns([
                TextColumn::make('name.en')
                    ->label('Name (EN)')
                    ->searchable(query: static fn ($query, string $search) => $query->where('name->en', 'like', "%{$search}%"))
                    ->sortable(),

                TextColumn::make('name.ar')
                    ->label('Name (AR)')
                    ->searchable(query: static fn ($query, string $search) => $query->where('name->ar', 'like', "%{$search}%")),

                TextColumn::make('parent.slug')
                    ->label(__('admin.fields.parent'))
                    ->placeholder('—'),

                TextColumn::make('slug')
                    ->label(__('admin.fields.slug'))
                    ->searchable(),

                TextColumn::make('icon')
                    ->label(__('admin.fields.icon'))
                    ->toggleable(),

                TextColumn::make('order')
                    ->label(__('admin.fields.order'))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('admin.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('admin.fields.is_active')),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'view' => Pages\ViewCategory::route('/{record}'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }

    /**
     * Bust every cache key the public CategoryService might be holding onto.
     * Cheap-and-broad on purpose — see class docblock.
     */
    public static function flushCache(): void
    {
        Cache::forget('categories.tree');
        Cache::forget('categories.main');
        // Per-category filter / fields caches use slug-suffixed keys; we tag
        // them under a common prefix so we wipe the slug-suffixed entries
        // collectively. If the cache driver doesn't support tags we fall
        // back to a no-op and rely on TTL.
        try {
            Cache::tags(['categories'])->flush();
        } catch (Throwable) {
            // Driver doesn't support tags (file / array) — fine, keys
            // expire by TTL.
        }
    }
}
