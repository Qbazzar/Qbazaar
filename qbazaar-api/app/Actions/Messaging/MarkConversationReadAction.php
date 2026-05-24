<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Events\Messaging\ConversationRead;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
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
    public function execute(User $reader, Conversation $conversation): int
    {
        $now = Carbon::now();

        $updated = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->update(['read_at' => $now]);

        if ($updated > 0) {
            DB::afterCommit(function () use ($conversation, $reader): void {
                ConversationRead::dispatch($conversation, $reader);
            });
        }

        return $updated;
    }
}
