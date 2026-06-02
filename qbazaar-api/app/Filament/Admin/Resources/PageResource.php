<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PageResource\Pages;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

/**
 * CMS page editor — drives the `/p/{slug}` public surface (About, Terms,
 * Privacy, Contact, etc.). Slug is the URL key; title/body/meta_description
 * are bilingual JSON columns rendered through two RichEditors so admins can
 * paste copy without context-switching to a Markdown editor.
 *
 * `flushCache()` busts both the index cache and the per-slug cache so the
 * public app picks up changes immediately.
 */
class PageResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 80;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.navigation_groups.content');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.pages');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.page.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.page.plural');
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

                    TextInput::make('display_order')
                        ->label(__('admin.fields.order'))
                        ->numeric()
                        ->default(0),

                    Toggle::make('is_published')
                        ->label(__('admin.fields.is_published'))
                        ->default(true),

                    DateTimePicker::make('published_at')
                        ->label(__('admin.fields.published_at'))
                        ->seconds(false),
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

            Section::make(__('admin.sections.seo'))
                ->collapsed()
                ->schema([
                    KeyValue::make('meta_description')
                        ->label(__('admin.fields.meta_description'))
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

                    IconEntry::make('is_published')
                        ->label(__('admin.fields.is_published'))
                        ->boolean(),

                    TextEntry::make('display_order')
                        ->label(__('admin.fields.order'))
                        ->numeric(),

                    TextEntry::make('published_at')
                        ->label(__('admin.fields.published_at'))
                        ->dateTime()
                        ->since()
                        ->placeholder('—'),

                    TextEntry::make('updated_at')
                        ->label(__('admin.fields.updated_at'))
                        ->dateTime()
                        ->since(),

                    TextEntry::make('created_at')
                        ->label(__('admin.fields.created_at'))
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

            Section::make(__('admin.sections.seo'))
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextEntry::make('meta_description.ar')
                        ->label(__('admin.locales.ar'))
                        ->placeholder('—'),

                    TextEntry::make('meta_description.en')
                        ->label(__('admin.locales.en'))
                        ->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('display_order')
            ->defaultSort('display_order')
            ->columns([
                TextColumn::make('slug')
                    ->label(__('admin.fields.slug'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title.ar')
                    ->label(__('admin.fields.title_ar'))
                    ->searchable(query: static fn ($query, string $search) => $query->where('title->ar', 'like', "%{$search}%")),

                TextColumn::make('title.en')
                    ->label(__('admin.fields.title_en'))
                    ->searchable(query: static fn ($query, string $search) => $query->where('title->en', 'like', "%{$search}%"))
                    ->toggleable(),

                IconColumn::make('is_published')
                    ->label(__('admin.fields.is_published'))
                    ->boolean(),

                TextColumn::make('display_order')
                    ->label(__('admin.fields.order'))
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('admin.fields.updated'))
                    ->dateTime('Y-m-d H:i')
                    ->since(),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label(__('admin.fields.is_published')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->after(static fn (Page $record) => self::flushCache($record->slug)),
                DeleteAction::make()->after(static fn (Page $record) => self::flushCache($record->slug)),
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
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'view' => Pages\ViewPage::route('/{record}'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }

    public static function flushCache(?string $slug = null): void
    {
        Cache::forget('pages.list');
        if ($slug !== null) {
            Cache::forget("pages.show.{$slug}");
        }
    }
}
