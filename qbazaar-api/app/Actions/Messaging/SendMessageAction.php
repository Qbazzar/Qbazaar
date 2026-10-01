<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Enums\MessageType;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Messaging\ConversationMessageWriter;

/**
 * Appends a message to a conversation and pushes the realtime broadcast.
 *
 *  - Block status is re-checked at send time (not just at conversation
 *    creation) so a block applied later still gates new traffic. Throws
 *    MSG_BLOCKED (403).
 *  - {@see ConversationMessageWriter} writes the message and the inbox
 *    preview in one transaction and broadcasts MessageSent after commit.
 *
 * Participants check is the caller's responsibility — they should have
 * already authorised the action via ConversationPolicy::send. We don't
 * silently authorise here so the policy violation surfaces as the
 * standard 403 FORBIDDEN envelope.
 */
class SendMessageAction
{
    public function __construct(private readonly ConversationMessageWriter $messages) {}

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

        return $this->messages->append($conversation, $sender, $body, $type);
    }
}
