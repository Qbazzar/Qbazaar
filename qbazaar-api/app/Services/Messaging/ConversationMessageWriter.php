<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\MessageType;
use App\Events\Messaging\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Appends a message to a conversation, refreshes the inbox preview and
 * broadcasts it to the other participant once the surrounding transaction
 * commits.
 */
class ConversationMessageWriter
{
    public function append(Conversation $conversation, User $author, string $body, MessageType $type): Message
    {
        $conversation->loadMissing(['buyer', 'seller']);
        $recipient = $conversation->otherParticipant($author);

        /** @var Message $message */
        $message = DB::transaction(function () use ($conversation, $author, $body, $type): Message {
            /** @var Message $created */
            $created = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $author->id,
                'body' => $body,
                'type' => $type->value,
            ]);

            $conversation->forceFill([
                'last_message_at' => $created->created_at,
                'last_message_preview' => Str::limit($body, 160),
            ])->save();

            return $created;
        });

        $message->setRelation('sender', $author);

        DB::afterCommit(function () use ($message, $conversation, $recipient): void {
            MessageSent::dispatch($message, $conversation->fresh() ?? $conversation, $recipient);
        });

        return $message;
    }
}
