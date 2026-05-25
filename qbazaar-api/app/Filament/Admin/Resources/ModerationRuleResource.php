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
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

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

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

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
            Select::make('type')
                ->label(__('admin.fields.type'))
                ->options([
                    ModerationRuleType::BANNED_WORD->value => 'Banned word',
                    ModerationRuleType::BLOCKED_DOMAIN->value => 'Blocked domain',
                ])
                ->required(),

            TextInput::make('value')
                ->label(__('admin.fields.value'))
                ->required()
                ->maxLength(255),

            Select::make('language')
                ->label(__('admin.fields.language_scope'))
                ->options([
                    ModerationRuleLanguage::ANY->value => 'Any',
                    ModerationRuleLanguage::AR->value => 'Arabic',
                    ModerationRuleLanguage::EN->value => 'English',
                ])
                ->required()
                ->default(ModerationRuleLanguage::ANY->value),

            Toggle::make('is_active')
                ->label(__('admin.fields.is_active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label(__('admin.fields.type'))
                    ->badge()
                    ->searchable(),

                TextColumn::make('value')
                    ->label(__('admin.fields.value'))
                    ->searchable(),

                TextColumn::make('language')
                    ->label(__('admin.fields.language_scope'))
                    ->badge(),

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
                        ModerationRuleType::BANNED_WORD->value => 'Banned word',
                        ModerationRuleType::BLOCKED_DOMAIN->value => 'Blocked domain',
                    ]),

                SelectFilter::make('language')
                    ->label(__('admin.fields.language_scope'))
                    ->options([
                        ModerationRuleLanguage::ANY->value => 'Any',
                        ModerationRuleLanguage::AR->value => 'Arabic',
                        ModerationRuleLanguage::EN->value => 'English',
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
