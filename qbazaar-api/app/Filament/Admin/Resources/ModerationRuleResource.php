<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\ModerationRuleLanguage;
use App\Enums\ModerationRuleType;
use App\Filament\Admin\Resources\ModerationRuleResource\Pages;
use App\Models\ModerationRule;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * CRUD over the moderation_rules table. The model's saved/deleted hooks bust
 * the rule cache, so this resource doesn't need any explicit `->after()`
 * lifecycle work — every mutation pathway converges on the same listener.
 */
class ModerationRuleResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = ModerationRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?int $navigationSort = 41;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.navigation_groups.moderation');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.moderation_rules');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.moderation_rule.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.moderation_rule.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->columns(2)
                ->schema([
                    Select::make('type')
                        ->label(__('admin.fields.type'))
                        ->options([
                            ModerationRuleType::BANNED_WORD->value => __('admin.moderation_rule.type.banned_word'),
                            ModerationRuleType::BLOCKED_DOMAIN->value => __('admin.moderation_rule.type.blocked_domain'),
                        ])
                        ->required(),

                    Select::make('language')
                        ->label(__('admin.fields.language_scope'))
                        ->options([
                            ModerationRuleLanguage::ANY->value => __('admin.moderation_rule.language.any'),
                            ModerationRuleLanguage::AR->value => __('admin.moderation_rule.language.ar'),
                            ModerationRuleLanguage::EN->value => __('admin.moderation_rule.language.en'),
                        ])
                        ->required()
                        ->default(ModerationRuleLanguage::ANY->value),

                    TextInput::make('value')
                        ->label(__('admin.fields.value'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Toggle::make('is_active')
                        ->label(__('admin.fields.is_active'))
                        ->default(true),
                ]),
        ])->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->columns(3)
                ->schema([
                    TextEntry::make('type')
                        ->label(__('admin.fields.type'))
                        ->badge()
                        ->color('warning')
                        ->formatStateUsing(static fn (ModerationRuleType $state): string => (string) __('admin.moderation_rule.type.' . $state->value)),

                    TextEntry::make('language')
                        ->label(__('admin.fields.language_scope'))
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(static fn (ModerationRuleLanguage $state): string => (string) __('admin.moderation_rule.language.' . $state->value)),

                    IconEntry::make('is_active')
                        ->label(__('admin.fields.is_active'))
                        ->boolean(),

                    TextEntry::make('value')
                        ->label(__('admin.fields.value'))
                        ->weight(FontWeight::SemiBold)
                        ->fontFamily(FontFamily::Mono)
                        ->copyable()
                        ->columnSpanFull(),

                    TextEntry::make('created_at')
                        ->label(__('admin.fields.created_at'))
                        ->dateTime()
                        ->since(),

                    TextEntry::make('updated_at')
                        ->label(__('admin.fields.updated_at'))
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
                    ->badge()
                    ->formatStateUsing(static fn (ModerationRuleType $state): string => (string) __('admin.moderation_rule.type.' . $state->value))
                    ->searchable(),

                TextColumn::make('value')
                    ->label(__('admin.fields.value'))
                    ->searchable(),

                TextColumn::make('language')
                    ->label(__('admin.fields.language_scope'))
                    ->badge()
                    ->formatStateUsing(static fn (ModerationRuleLanguage $state): string => (string) __('admin.moderation_rule.language.' . $state->value)),

                IconColumn::make('is_active')
                    ->label(__('admin.fields.is_active'))
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label(__('admin.fields.updated_at'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        ModerationRuleType::BANNED_WORD->value => __('admin.moderation_rule.type.banned_word'),
                        ModerationRuleType::BLOCKED_DOMAIN->value => __('admin.moderation_rule.type.blocked_domain'),
                    ]),

                SelectFilter::make('language')
                    ->label(__('admin.fields.language_scope'))
                    ->options([
                        ModerationRuleLanguage::ANY->value => __('admin.moderation_rule.language.any'),
                        ModerationRuleLanguage::AR->value => __('admin.moderation_rule.language.ar'),
                        ModerationRuleLanguage::EN->value => __('admin.moderation_rule.language.en'),
                    ]),

                TernaryFilter::make('is_active')->label(__('admin.fields.is_active')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModerationRules::route('/'),
            'create' => Pages\CreateModerationRule::route('/create'),
            'view' => Pages\ViewModerationRule::route('/{record}'),
            'edit' => Pages\EditModerationRule::route('/{record}/edit'),
        ];
    }
}
