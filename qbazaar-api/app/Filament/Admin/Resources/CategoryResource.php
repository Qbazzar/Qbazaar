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
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Throwable;

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
 *  - The View page renders an InfoList rather than a disabled-form fallback
 *    so translation pairs surface as side-by-side cards and optional fields
 *    collapse to a placeholder instead of empty boxes.
 */
class CategoryResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.navigation_groups.taxonomy');
    }

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
            Section::make(__('admin.sections.general'))
                ->columns(2)
                ->schema([
                    TextInput::make('slug')
                        ->label(__('admin.fields.slug'))
                        ->required()
                        ->maxLength(64)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),

                    Select::make('parent_id')
                        ->label(__('admin.fields.parent'))
                        ->relationship('parent', 'slug')
                        ->searchable()
                        ->preload(),

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
                        ->default(true)
                        ->columnSpanFull(),
                ]),

            Section::make(__('admin.sections.translations'))
                ->columns(1)
                ->schema([
                    KeyValue::make('name')
                        ->label(__('admin.fields.name'))
                        ->keyLabel(__('admin.fields.language'))
                        ->valueLabel(__('admin.fields.value'))
                        ->required()
                        ->default(['en' => '', 'ar' => '']),

                    KeyValue::make('description')
                        ->label(__('admin.fields.description'))
                        ->keyLabel(__('admin.fields.language'))
                        ->valueLabel(__('admin.fields.value'))
                        ->default(['en' => '', 'ar' => '']),
                ]),

            Section::make(__('admin.sections.meta'))
                ->collapsed()
                ->columns(1)
                ->schema([
                    KeyValue::make('custom_fields')
                        ->label(__('admin.fields.custom_fields'))
                        ->keyLabel(__('admin.fields.field_key'))
                        ->valueLabel(__('admin.fields.definition_json')),

                    KeyValue::make('custom_filters')
                        ->label(__('admin.fields.custom_filters'))
                        ->keyLabel(__('admin.fields.filter_key'))
                        ->valueLabel(__('admin.fields.definition_json')),
                ]),
        ])->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->columns(3)
                ->schema([
                    TextEntry::make('slug')
                        ->label(__('admin.fields.slug'))
                        ->weight(FontWeight::SemiBold)
                        ->copyable(),

                    TextEntry::make('parent.slug')
                        ->label(__('admin.fields.parent'))
                        ->placeholder('—'),

                    TextEntry::make('order')
                        ->label(__('admin.fields.order'))
                        ->numeric(),

                    IconEntry::make('is_active')
                        ->label(__('admin.fields.is_active'))
                        ->boolean(),

                    TextEntry::make('icon')
                        ->label(__('admin.fields.icon'))
                        ->placeholder('—')
                        ->badge()
                        ->color('gray'),

                    TextEntry::make('updated_at')
                        ->label(__('admin.fields.updated_at'))
                        ->dateTime()
                        ->since()
                        ->placeholder('—'),
                ]),

            Section::make(__('admin.sections.translations'))
                ->columns(2)
                ->schema([
                    TextEntry::make('name.ar')
                        ->label(__('admin.locales.ar'))
                        ->placeholder('—')
                        ->weight(FontWeight::Medium),

                    TextEntry::make('name.en')
                        ->label(__('admin.locales.en'))
                        ->placeholder('—')
                        ->weight(FontWeight::Medium),

                    TextEntry::make('description.ar')
                        ->label(__('admin.locales.ar') . ' — ' . __('admin.fields.description'))
                        ->placeholder('—')
                        ->columnSpanFull(),

                    TextEntry::make('description.en')
                        ->label(__('admin.locales.en') . ' — ' . __('admin.fields.description'))
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('order')
            ->defaultSort('order')
            ->columns([
                TextColumn::make('name.en')
                    ->label(__('admin.fields.name_en'))
                    ->searchable(query: static fn ($query, string $search) => $query->where('name->en', 'like', "%{$search}%"))
                    ->sortable(),

                TextColumn::make('name.ar')
                    ->label(__('admin.fields.name_ar'))
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
