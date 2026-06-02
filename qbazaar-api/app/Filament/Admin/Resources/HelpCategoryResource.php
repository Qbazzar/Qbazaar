<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\HelpCategoryResource\Pages;
use App\Models\HelpCategory;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

/**
 * Help center category editor. Drives `/help/c/{slug}` on the public site.
 */
class HelpCategoryResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = HelpCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?int $navigationSort = 81;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.navigation_groups.content');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.help_categories');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.help_category.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.help_category.plural');
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

                    TextInput::make('icon')
                        ->label(__('admin.fields.icon'))
                        ->helperText(__('admin.helpers.lucide_icon'))
                        ->maxLength(64),

                    TextInput::make('display_order')
                        ->label(__('admin.fields.order'))
                        ->numeric()
                        ->default(0),
                ]),

            Section::make(__('admin.sections.translations'))
                ->columns(1)
                ->schema([
                    KeyValue::make('name')
                        ->label(__('admin.fields.name'))
                        ->keyLabel(__('admin.fields.language'))
                        ->valueLabel(__('admin.fields.value'))
                        ->required()
                        ->default(['ar' => '', 'en' => '']),

                    KeyValue::make('description')
                        ->label(__('admin.fields.description'))
                        ->keyLabel(__('admin.fields.language'))
                        ->valueLabel(__('admin.fields.value')),
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

                    TextEntry::make('icon')
                        ->label(__('admin.fields.icon'))
                        ->placeholder('—')
                        ->badge()
                        ->color('gray')
                        ->fontFamily(FontFamily::Mono),

                    TextEntry::make('display_order')
                        ->label(__('admin.fields.order'))
                        ->numeric(),

                    TextEntry::make('articles_count')
                        ->label(__('admin.resources.help_article.plural'))
                        ->state(static fn (HelpCategory $record): int => $record->articles()->count())
                        ->badge()
                        ->color('info'),

                    TextEntry::make('created_at')
                        ->label(__('admin.fields.created_at'))
                        ->dateTime()
                        ->since(),

                    TextEntry::make('updated_at')
                        ->label(__('admin.fields.updated_at'))
                        ->dateTime()
                        ->since(),
                ]),

            Section::make(__('admin.sections.translations'))
                ->columns(2)
                ->schema([
                    TextEntry::make('name.ar')
                        ->label(__('admin.locales.ar'))
                        ->weight(FontWeight::Medium)
                        ->placeholder('—'),

                    TextEntry::make('name.en')
                        ->label(__('admin.locales.en'))
                        ->weight(FontWeight::Medium)
                        ->placeholder('—'),

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
            ->reorderable('display_order')
            ->defaultSort('display_order')
            ->columns([
                TextColumn::make('name.ar')
                    ->label(__('admin.fields.name_ar'))
                    ->searchable(query: static fn ($query, string $search) => $query->where('name->ar', 'like', "%{$search}%")),

                TextColumn::make('name.en')
                    ->label(__('admin.fields.name_en'))
                    ->searchable(query: static fn ($query, string $search) => $query->where('name->en', 'like', "%{$search}%"))
                    ->toggleable(),

                TextColumn::make('slug')->label(__('admin.fields.slug'))->searchable(),

                TextColumn::make('icon')->label(__('admin.fields.icon'))->toggleable(),

                TextColumn::make('articles_count')
                    ->label(__('admin.fields.articles'))
                    ->counts('articles')
                    ->sortable(),

                TextColumn::make('display_order')
                    ->label(__('admin.fields.order'))
                    ->sortable(),
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
            'index' => Pages\ListHelpCategories::route('/'),
            'create' => Pages\CreateHelpCategory::route('/create'),
            'view' => Pages\ViewHelpCategory::route('/{record}'),
            'edit' => Pages\EditHelpCategory::route('/{record}/edit'),
        ];
    }

    public static function flushCache(): void
    {
        Cache::forget('help.categories');
    }
}
