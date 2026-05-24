<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Enums\MessageType;
use App\Events\Messaging\MessageSent;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Appends a message to a conversation and pushes the realtime broadcast.
 *
 *  - Block status is re-checked at send time (not just at conversation
 *    creation) so a block applied later still gates new traffic. Throws
 *    MSG_BLOCKED (403).
 *  - The DB write + denormalised preview update happen inside a single
 *    transaction so a partial write can never leave the inbox out of sync
 *    with the message log.
 *  - MessageSent is dispatched via `DB::afterCommit` so subscribers (and
 *    the Reverb broadcast) never see a row that doesn't exist yet.
 *
 * Participants check is the caller's responsibility — they should have
 * already authorised the action via ConversationPolicy::send. We don't
 * silently authorise here so the policy violation surfaces as the
 * standard 403 FORBIDDEN envelope.
 */
class SendMessageAction
{
    public function execute(
        User $sender,
        Conversation $conversation,
        string $body,
        MessageType $type = MessageType::TEXT,
    ): Message {
        $conversation->loadMissing(['buyer', 'seller']);
        $other = $conversation->otherParticipant($sender);

        if ($sender->hasBlocked($other) || $other->hasBlocked($sender)) {
            throw new DomainException(ErrorCode::MSG_BLOCKED);
        }

        $message = DB::transaction(function () use ($sender, $conversation, $body, $type): Message {
            /** @var Message $created */
            $created = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => $body,
                'type' => $type->value,
            ]);

            $conversation->forceFill([
                'last_message_at' => $created->created_at,
                'last_message_preview' => Str::limit($body, 160),
            ])->save();

            return $created;
        });

        // Eager-load sender so the broadcast payload + the controller
        // response can render the sender mini-object without a follow-up
        // query.
        $message->load('sender');

        DB::afterCommit(function () use ($message, $conversation, $other): void {
            MessageSent::dispatch($message, $conversation->fresh() ?? $conversation, $other);
        });

        return $message;
    }
}
