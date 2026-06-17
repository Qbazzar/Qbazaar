<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\MessageType;
use App\Filament\Admin\Resources\MessageResource\Pages;
use App\Models\Message;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only admin view of individual chat messages. Mostly useful for
 * moderation drill-down from a Report on a specific message.
 */
class MessageResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = Message::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?int $navigationSort = 52;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.navigation_groups.communications');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.messages');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.message.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.message.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->columns(3)
                ->schema([
                    TextEntry::make('sender.full_name')
                        ->label(__('admin.fields.sender'))
                        ->placeholder('—'),

                    TextEntry::make('type')
                        ->label(__('admin.fields.type'))
                        ->badge()
                        ->formatStateUsing(static fn (MessageType $state): string => (string) __('admin.message.type.' . $state->value)),

                    TextEntry::make('conversation.ad.title')
                        ->label(__('admin.fields.ad'))
                        ->placeholder('—'),

                    TextEntry::make('body')
                        ->label(__('admin.fields.body'))
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

                    TextEntry::make('read_at')
                        ->label(__('admin.fields.read_at'))
                        ->dateTime()
                        ->since()
                        ->placeholder('—'),

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
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with([
                'sender:id,full_name',
                'conversation:id,ad_id',
            ]))
            ->columns([
                TextColumn::make('sender.full_name')
                    ->label(__('admin.fields.subject'))
                    ->searchable(),

                TextColumn::make('body')
                    ->label(__('admin.fields.body'))
                    ->limit(80)
                    ->searchable(),

                TextColumn::make('type')
                    ->label(__('admin.fields.type'))
                    ->badge()
                    ->formatStateUsing(static fn (MessageType $state): string => (string) __('admin.message.type.' . $state->value)),

                TextColumn::make('conversation.ad_id')
                    ->label(__('admin.fields.ad'))
                    ->limit(10),

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
            'index' => Pages\ListMessages::route('/'),
            'view' => Pages\ViewMessage::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
