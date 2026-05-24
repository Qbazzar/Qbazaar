<?php

declare(strict_types=1);

namespace App\Events\Messaging;

use App\Http\Resources\Api\V1\Messaging\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Pushed to Reverb the moment a message is persisted (after DB commit).
 *
 * Broadcasts on TWO private channels:
 *   1. `conversation.{conversationId}` — clients with the conversation
 *      open subscribe here to append the bubble in real-time.
 *   2. `user.{otherUserId}` — the recipient subscribes per-session so
 *      the header unread badge updates even when they're not on the
 *      conversation screen.
 *
 * Payload mirrors `MessageResource` plus a `conversation` envelope so the
 * inbox list can update without a refetch:
 *   - `last_message_preview`, `last_message_at` for the row preview.
 *   - `unread_count` for the recipient's badge.
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $otherUserId;

    public function __construct(
        public Message $message,
        public Conversation $conversation,
        public User $recipient,
    ) {
        $this->otherUserId = $recipient->id;
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
            new PrivateChannel('user.' . $this->otherUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // The MessageResource needs a Request to satisfy its signature;
        // a synthetic one is enough — the resource doesn't read any of
        // its properties.
        $request = Request::create('/internal/broadcast', 'GET');

        $this->message->loadMissing('sender');

        return [
            'message' => (new MessageResource($this->message))->toArray($request),
            'conversation' => [
                'id' => $this->conversation->id,
                'last_message_preview' => $this->conversation->last_message_preview,
                'last_message_at' => $this->conversation->last_message_at?->toIso8601String(),
                'unread_count' => $this->conversation->unreadCountFor($this->recipient),
            ],
        ];
    }
}
