<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ConversationResource\Pages;
use App\Models\Conversation;
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
 * Read-only admin window over the buyer↔seller chat threads.
 *
 * We do NOT allow editing or deleting from here — message content is
 * load-bearing audit data for moderation. The view page shows the full
 * transcript inline so a moderator reviewing a related report can read the
 * context without paging through messages.
 */
class ConversationResource extends Resource
{
    /** @var class-string|null */
    protected static ?string $model = Conversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 51;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.navigation_groups.communications');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.navigation.conversations');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.resources.conversation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.resources.conversation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->columns(2)
                ->schema([
                    TextEntry::make('ad.title')
                        ->label(__('admin.fields.ad'))
                        ->placeholder('—')
                        ->columnSpanFull(),

                    TextEntry::make('buyer.full_name')
                        ->label(__('admin.fields.buyer'))
                        ->placeholder('—'),

                    TextEntry::make('seller.full_name')
                        ->label(__('admin.fields.seller'))
                        ->placeholder('—'),

                    TextEntry::make('messages_count')
                        ->label(__('admin.fields.message_count'))
                        ->state(static fn (Conversation $record): int => $record->messages()->count())
                        ->badge(),
                ]),

            Section::make(__('admin.sections.audit'))
                ->columns(3)
                ->schema([
                    TextEntry::make('id')
                        ->label(__('admin.fields.id'))
                        ->copyable()
                        ->fontFamily(FontFamily::Mono),

                    TextEntry::make('last_message_at')
                        ->label(__('admin.fields.last_message_at'))
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
                'ad:id,title',
                'buyer:id,full_name,email',
                'seller:id,full_name,email',
            ])->withCount('messages'))
            ->columns([
                TextColumn::make('ad.title')
                    ->label(__('admin.fields.ad'))
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('buyer.full_name')
                    ->label(__('admin.fields.buyer'))
                    ->searchable(),

                TextColumn::make('seller.full_name')
                    ->label(__('admin.fields.seller'))
                    ->searchable(),

                TextColumn::make('messages_count')
                    ->label(__('admin.fields.message_count'))
                    ->sortable(),

                TextColumn::make('last_message_at')
                    ->label(__('admin.fields.last_message_at'))
                    ->dateTime()
                    ->since()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('last_message_at', 'desc');
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConversations::route('/'),
            'view' => Pages\ViewConversation::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
