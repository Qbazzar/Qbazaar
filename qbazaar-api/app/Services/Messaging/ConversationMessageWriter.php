<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Data\Messaging\ChatMessageDraft;
use App\Enums\ChatMessageKey;
use App\Enums\Language;
use App\Events\Messaging\MessageSent;
use App\Events\Messaging\UnreadMessagesChanged;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Media\UploadedFileNamer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Appends a message to a conversation, refreshes the inbox preview and
 * broadcasts it to the other participant once the surrounding transaction
 * commits.
 */
class ConversationMessageWriter
{
    public function __construct(
        private readonly ChatMessageRenderer $renderer,
        private readonly UploadedFileNamer $fileNamer,
    ) {}

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
                'body' => $this->english($draft->key, $draft),
                'type' => $draft->type,
                'message_key' => $draft->key?->value,
                'params' => $this->storedParams($draft),
            ]);

            // Stored before the conversation row is touched, so the upload
            // never holds that row's lock.
            if ($draft->image instanceof UploadedFile) {
                $this->attachImage($created, $draft->image);
            }

            $previewKey = $draft->previewKey();

            $conversation->forceFill([
                'last_message_at' => $created->created_at,
                'last_message_preview' => Str::limit($this->english($previewKey, $draft), ChatMessageRenderer::PREVIEW_LENGTH),
                'last_message_key' => $previewKey?->value,
                'last_message_params' => $created->params,
            ])->save();

            return $created;
        });

        $message->setRelation('sender', $author);

        DB::afterCommit(function () use ($message, $conversation, $recipient): void {
            MessageSent::dispatch($message, $conversation->fresh() ?? $conversation, $recipient);
            UnreadMessagesChanged::dispatch($recipient->id);
        });

        return $message;
    }

    /**
     * Keyed bubbles also keep an English rendering, so staff screens,
     * moderation and clients that predate keys still read them.
     */
    private function english(?ChatMessageKey $key, ChatMessageDraft $draft): string
    {
        if ($key === null) {
            return $draft->body;
        }

        return $this->renderer->render($key->value, $draft->params, $draft->body, Language::ENGLISH->value);
    }

    /**
     * @return array<string, scalar|null>|null
     */
    private function storedParams(ChatMessageDraft $draft): ?array
    {
        return $draft->key !== null && $draft->params !== [] ? $draft->params : null;
    }

    private function attachImage(Message $message, UploadedFile $image): void
    {
        $message->addMedia($image)
            ->usingFileName($this->fileNamer->nameFor($image))
            ->toMediaCollection(Message::IMAGE_COLLECTION);

        $message->load('media');
    }
}
