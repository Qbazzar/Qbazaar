<?php

declare(strict_types=1);

namespace App\Events\Messaging;

use App\Enums\MessageType;
use App\Events\Concerns\BroadcastsOnRealtimeQueue;
use App\Http\Resources\Api\V1\Messaging\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Messaging\ChatMessageRenderer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Traits\Localizable;

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
    use BroadcastsOnRealtimeQueue, Dispatchable, InteractsWithSockets, Localizable, SerializesModels;

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
     * Rendered in the recipient's language; the sender's own devices on the
     * conversation channel re-render from `message_key` + `params`.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->withLocale($this->recipient->language->value, function (): array {
            // MessageResource only needs a Request to satisfy its signature.
            $request = Request::create('/internal/broadcast', 'GET');

            $this->message->loadMissing($this->message->type === MessageType::IMAGE ? ['sender', 'media'] : ['sender']);

            return [
                'message' => (new MessageResource($this->message))->toArray($request),
                'conversation' => [
                    'id' => $this->conversation->id,
                    'last_message_preview' => app(ChatMessageRenderer::class)->preview($this->conversation),
                    'last_message_key' => $this->conversation->last_message_key,
                    'last_message_params' => $this->conversation->last_message_params,
                    'last_message_at' => $this->conversation->last_message_at?->toIso8601String(),
                    'unread_count' => $this->conversation->unreadCountFor($this->recipient),
                ],
            ];
        });
    }
}
