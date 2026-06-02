<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SupportTicketResource\RelationManagers;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

/**
 * Replies thread for a SupportTicket. Staff replies created here are
 * auto-flagged `is_staff=true` and the parent ticket's `last_replied_at`
 * + `status` are nudged forward (in_progress → waiting_user on a staff
 * reply).
 */
class RepliesRelationManager extends RelationManager
{
    protected static string $relationship = 'replies';

    protected static ?string $title = 'Conversation thread';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->label(__('admin.fields.reply'))
                ->required()
                ->rows(5)
                ->maxLength(5000)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('author.full_name')
                    ->label(__('admin.fields.author')),

                IconColumn::make('is_staff')
                    ->label(__('admin.fields.is_staff'))
                    ->boolean(),

                TextColumn::make('body')
                    ->label(__('admin.fields.body'))
                    ->wrap()
                    ->limit(120),

                TextColumn::make('created_at')
                    ->label(__('admin.fields.sent'))
                    ->dateTime('Y-m-d H:i')
                    ->since(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('admin.fields.reply_as_staff'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['author_id'] = (string) auth()->id();
                        $data['is_staff'] = true;

                        return $data;
                    })
                    ->after(function (): void {
                        /** @var SupportTicket $ticket */
                        $ticket = $this->getOwnerRecord();

                        $patch = ['last_replied_at' => Carbon::now()];
                        if ($ticket->status === SupportTicketStatus::OPEN) {
                            $patch['status'] = SupportTicketStatus::IN_PROGRESS->value;
                        } elseif ($ticket->status === SupportTicketStatus::IN_PROGRESS) {
                            $patch['status'] = SupportTicketStatus::WAITING_USER->value;
                        }
                        $ticket->forceFill($patch)->save();
                    }),
            ]);
    }
}
