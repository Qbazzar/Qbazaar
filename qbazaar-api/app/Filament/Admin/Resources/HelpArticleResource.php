<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\HelpArticleResource\Pages;
use App\Models\HelpArticle;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

/**
 * Help article editor. Drives `/help/articles/{slug}` on the public site.
 * The same `flushCache()` busts both the category index (since article
 * counts change) and the per-article cache when implemented.
 */
class HelpArticleResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = HelpArticle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 82;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.navigation_groups.content');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.help_articles');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.help_article.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.help_article.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->columns(2)
                ->schema([
                    Select::make('category_id')
                        ->label(__('admin.fields.category'))
                        ->relationship('category', 'slug')
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('slug')
                        ->label(__('admin.fields.slug'))
                        ->required()
                        ->maxLength(120)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),

                    TextInput::make('display_order')
                        ->label(__('admin.fields.order'))
                        ->numeric()
                        ->default(0),

                    Toggle::make('is_published')
                        ->label(__('admin.fields.is_published'))
                        ->default(true),
                ]),

            Section::make(__('admin.sections.translations'))
                ->columns(1)
                ->schema([
                    KeyValue::make('title')
                        ->label(__('admin.fields.title'))
                        ->keyLabel(__('admin.fields.language'))
                        ->valueLabel(__('admin.fields.value'))
                        ->required()
                        ->default(['ar' => '', 'en' => '']),

                    KeyValue::make('excerpt')
                        ->label(__('admin.fields.excerpt'))
                        ->keyLabel(__('admin.fields.language'))
                        ->valueLabel(__('admin.fields.short_summary')),
                ]),

            Section::make(__('admin.sections.content'))
                ->schema([
                    Tabs::make('body_tabs')
                        ->columnSpanFull()
                        ->tabs([
                            Tab::make(__('admin.locales.ar'))->schema([
                                RichEditor::make('body.ar')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->required()
                                    ->columnSpanFull(),
                            ]),
                            Tab::make(__('admin.locales.en'))->schema([
                                RichEditor::make('body.en')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->required()
                                    ->columnSpanFull(),
                            ]),
                        ]),
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

                    TextEntry::make('category.slug')
                        ->label(__('admin.fields.category'))
                        ->badge()
                        ->color('primary')
                        ->placeholder('—'),

                    IconEntry::make('is_published')
                        ->label(__('admin.fields.is_published'))
                        ->boolean(),

                    TextEntry::make('display_order')
                        ->label(__('admin.fields.order'))
                        ->numeric(),

                    TextEntry::make('views_count')
                        ->label(__('admin.fields.views_count'))
                        ->numeric(),

                    TextEntry::make('updated_at')
                        ->label(__('admin.fields.updated_at'))
                        ->dateTime()
                        ->since(),
                ]),

            Section::make(__('admin.sections.translations'))
                ->columns(2)
                ->schema([
                    TextEntry::make('title.ar')
                        ->label(__('admin.locales.ar'))
                        ->weight(FontWeight::Medium)
                        ->placeholder('—'),

                    TextEntry::make('title.en')
                        ->label(__('admin.locales.en'))
                        ->weight(FontWeight::Medium)
                        ->placeholder('—'),

                    TextEntry::make('excerpt.ar')
                        ->label(__('admin.locales.ar') . ' — Excerpt')
                        ->placeholder('—')
                        ->columnSpanFull(),

                    TextEntry::make('excerpt.en')
                        ->label(__('admin.locales.en') . ' — Excerpt')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

            Section::make(__('admin.sections.content'))
                ->schema([
                    Tabs::make('body_tabs')
                        ->columnSpanFull()
                        ->tabs([
                            Tab::make(__('admin.locales.ar'))->schema([
                                TextEntry::make('body.ar')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->html()
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                            Tab::make(__('admin.locales.en'))->schema([
                                TextEntry::make('body.en')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->html()
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('display_order')
            ->defaultSort('display_order')
            ->columns([
                TextColumn::make('title.ar')
                    ->label(__('admin.fields.title_ar'))
                    ->searchable(query: static fn ($query, string $search) => $query->where('title->ar', 'like', "%{$search}%"))
                    ->wrap(),

                TextColumn::make('category.slug')
                    ->label(__('admin.fields.category'))
                    ->sortable(),

                TextColumn::make('slug')
                    ->label(__('admin.fields.slug'))
                    ->searchable()
                    ->toggleable(),

                IconColumn::make('is_published')
                    ->label(__('admin.fields.is_published'))
                    ->boolean(),

                TextColumn::make('views_count')
                    ->label(__('admin.fields.views'))
                    ->sortable(),

                TextColumn::make('display_order')
                    ->label(__('admin.fields.order'))
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('admin.fields.updated'))
                    ->dateTime('Y-m-d H:i')
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('admin.fields.category'))
                    ->relationship('category', 'slug'),

                TernaryFilter::make('is_published')->label(__('admin.fields.is_published')),
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
            'index' => Pages\ListHelpArticles::route('/'),
            'create' => Pages\CreateHelpArticle::route('/create'),
            'view' => Pages\ViewHelpArticle::route('/{record}'),
            'edit' => Pages\EditHelpArticle::route('/{record}/edit'),
        ];
    }

    public static function flushCache(): void
    {
        Cache::forget('help.categories');
    }
}
