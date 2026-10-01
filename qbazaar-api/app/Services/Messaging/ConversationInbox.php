<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Per-user inbox state kept in `conversation_participants`: one row per side
 * with the hide stamp, the unread counter and the conversation's sort keys.
 *
 * The counter moves with the messages themselves: +1 for the recipient
 * when an unread message is stored, -N when N messages are marked read.
 * Both are single atomic UPDATEs, so concurrent sends and reads never lose
 * a change and need no lock.
 */
class ConversationInbox
{
    private const TABLE = 'conversation_participants';

    public function open(Conversation $conversation): void
    {
        $row = [
            'conversation_id' => $conversation->id,
            'last_message_at' => $conversation->last_message_at,
            'conversation_created_at' => $conversation->created_at,
        ];

        DB::table(self::TABLE)->insertOrIgnore([
            [...$row, 'user_id' => $conversation->buyer_id],
            [...$row, 'user_id' => $conversation->seller_id],
        ]);
    }

    public function resort(Conversation $conversation): void
    {
        $this->rows($conversation->id)->update(['last_message_at' => $conversation->last_message_at]);
    }

    /** A new message brings the thread back for anyone who hid it and counts as unread for the recipient. */
    public function messageAdded(Message $message): void
    {
        DB::update(
            'UPDATE ' . self::TABLE . ' SET hidden_at = NULL, unread_count = unread_count + CASE WHEN user_id = ? THEN 0 ELSE ? END WHERE conversation_id = ?',
            [$message->sender_id, $message->read_at === null ? 1 : 0, $message->conversation_id],
        );
    }

    public function messagesRead(Conversation $conversation, User $reader, int $count): void
    {
        if ($count <= 0) {
            return;
        }

        DB::update(
            'UPDATE ' . self::TABLE . ' SET unread_count = CASE WHEN unread_count > ? THEN unread_count - ? ELSE 0 END WHERE conversation_id = ? AND user_id = ?',
            [$count, $count, $conversation->id, $reader->id],
        );
    }

    /**
     * @param list<string> $conversationIds
     * @return int conversations hidden; ids that are not the user's are ignored
     */
    public function hide(User $user, array $conversationIds): int
    {
        return DB::table(self::TABLE)
            ->where('user_id', $user->id)
            ->whereIn('conversation_id', $conversationIds)
            ->update(['hidden_at' => now()]);
    }

    /**
     * The user's visible conversations, newest activity first, each with its
     * `unread_count`. Walks `conversation_participants_inbox_idx` backwards,
     * so a page costs one index range and no sort.
     *
     * @return Builder<Conversation>
     */
    public function conversationsOf(User $user): Builder
    {
        return Conversation::query()
            ->join(self::TABLE . ' as inbox', 'inbox.conversation_id', '=', 'conversations.id')
            ->where('inbox.user_id', $user->id)
            ->whereNull('inbox.hidden_at')
            ->orderByDesc('inbox.last_message_at')
            ->orderByDesc('inbox.conversation_created_at')
            ->select(['conversations.*', 'inbox.unread_count']);
    }

    public function visibleCount(string $userId): int
    {
        return $this->visibleRows($userId)->count();
    }

    /** The chat badge: unread messages across the user's visible conversations. */
    public function unreadTotal(string $userId): int
    {
        return (int) $this->visibleRows($userId)->sum('unread_count');
    }

    public function unreadIn(Conversation $conversation, User $user): int
    {
        return (int) $this->rows($conversation->id)->where('user_id', $user->id)->value('unread_count');
    }

    private function visibleRows(string $userId): QueryBuilder
    {
        return DB::table(self::TABLE)->where('user_id', $userId)->whereNull('hidden_at');
    }

    private function rows(string $conversationId): QueryBuilder
    {
        return DB::table(self::TABLE)->where('conversation_id', $conversationId);
    }
}
