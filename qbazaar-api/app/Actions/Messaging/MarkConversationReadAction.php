<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Events\Messaging\ConversationRead;
use App\Events\Messaging\UnreadMessagesChanged;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Messaging\ConversationInbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-stamps `read_at` on every unread message in the conversation that
 * was sent by the other party. Returns the row count so the caller can
 * decide whether to fire the read-receipt broadcast (no need to disturb
 * the other side over zero updates).
 */
class MarkConversationReadAction
{
    public function __construct(private readonly ConversationInbox $inbox) {}

    public function execute(User $reader, Conversation $conversation): int
    {
        $updated = DB::transaction(function () use ($reader, $conversation): int {
            $updated = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('sender_id', '!=', $reader->id)
                ->whereNull('read_at')
                ->update(['read_at' => Carbon::now()]);

            $this->inbox->messagesRead($conversation, $reader, $updated);

            return $updated;
        });

        if ($updated > 0) {
            DB::afterCommit(function () use ($conversation, $reader): void {
                ConversationRead::dispatch($conversation, $reader);
                UnreadMessagesChanged::dispatch($reader->id);
            });
        }

        return $updated;
    }
}
