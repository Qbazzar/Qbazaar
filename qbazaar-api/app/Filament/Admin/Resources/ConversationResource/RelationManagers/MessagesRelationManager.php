<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ConversationResource\RelationManagers;

use App\Enums\MessageType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only full message thread for a conversation. Surfaced on the admin
 * ConversationResource view page so a moderator can read the entire exchange
 * in order without leaving the page. Messages are audit data — no create /
 * edit / delete here.
 */
class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Messages';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'asc')
            ->modifyQueryUsing(static fn ($query) => $query->with('sender:id,full_name')->reorder()->orderBy('created_at'))
            ->columns([
                TextColumn::make('sender.full_name')
                    ->label(__('admin.fields.sender'))
                    ->weight(FontWeight::Medium),

                TextColumn::make('type')
                    ->label(__('admin.fields.type'))
                    ->badge()
                    ->formatStateUsing(static fn (MessageType $state): string => (string) __('admin.message.type.' . $state->value)),

                TextColumn::make('body')
                    ->label(__('admin.fields.body'))
                    ->wrap()
                    ->extraAttributes(['style' => 'white-space: pre-wrap; max-width: 640px;']),

                TextColumn::make('created_at')
                    ->label(__('admin.fields.sent'))
                    ->dateTime('Y-m-d H:i')
                    ->since(),
            ]);
    }
}
