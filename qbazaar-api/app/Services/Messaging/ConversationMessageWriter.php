<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Data\Messaging\ChatMessageDraft;
use App\Enums\Language;
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
    public function __construct(private readonly ChatMessageRenderer $renderer) {}

    public function append(Conversation $conversation, User $author, ChatMessageDraft $draft): Message
    {
        $conversation->loadMissing(['buyer', 'seller']);
        $recipient = $conversation->otherParticipant($author);

        /** @var Message $message */
        $message = DB::transaction(function () use ($conversation, $author, $draft): Message {
            /** @var Message $created */
            $created = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $author->id,
                'body' => $this->storedBody($draft),
                'type' => $draft->type,
                'message_key' => $draft->key?->value,
                'params' => $draft->key !== null && $draft->params !== [] ? $draft->params : null,
            ]);

            $conversation->forceFill([
                'last_message_at' => $created->created_at,
                'last_message_preview' => Str::limit($created->body, ChatMessageRenderer::PREVIEW_LENGTH),
                'last_message_key' => $created->message_key,
                'last_message_params' => $created->params,
            ])->save();

            return $created;
        });

        $message->setRelation('sender', $author);

        DB::afterCommit(function () use ($message, $conversation, $recipient): void {
            MessageSent::dispatch($message, $conversation->fresh() ?? $conversation, $recipient);
        });

        return $message;
    }

    /**
     * Keyed bubbles also keep an English rendering in `body`, so staff
     * screens, moderation and clients that predate keys still read them.
     */
    private function storedBody(ChatMessageDraft $draft): string
    {
        if ($draft->key === null) {
            return $draft->body;
        }

        return $this->renderer->render($draft->key->value, $draft->params, $draft->body, Language::ENGLISH->value);
    }
}
