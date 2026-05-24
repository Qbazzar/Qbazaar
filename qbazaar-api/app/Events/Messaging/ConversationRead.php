<?php

declare(strict_types=1);

namespace App\Events\Messaging;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Pushed when one participant marks the conversation as read. Only the
 * OTHER participant cares (so they can dim their "delivered" indicators to
 * "read"), so we broadcast on their personal user channel rather than the
 * shared conversation channel — keeps the read-receipt UI responsive
 * without re-fetching the entire transcript.
 */
class ConversationRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $conversationId;

    public string $readerId;

    public string $otherUserId;

    public Carbon $readAt;

    public function __construct(
        Conversation $conversation,
        User $reader,
    ) {
        $this->conversationId = $conversation->id;
        $this->readerId = $reader->id;
        $this->otherUserId = $conversation->otherParticipantId($reader);
        $this->readAt = Carbon::now();
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->otherUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'reader_id' => $this->readerId,
            'read_at' => $this->readAt->toIso8601String(),
        ];
    }
}
