<?php

declare(strict_types=1);

namespace App\Events\Messaging;

use App\Events\Concerns\BroadcastsOnRealtimeQueue;
use App\Services\Messaging\ConversationInbox;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The user's chat badge total, pushed to their own channel whenever it can
 * change: a message for them, a read on any of their devices, a hide.
 * Clients drop the unread-count polling to a slow fallback.
 *
 * The total is read when the broadcast runs, so a burst of messages always
 * ends on the latest value.
 */
class UnreadMessagesChanged implements ShouldBroadcast
{
    use BroadcastsOnRealtimeQueue, Dispatchable, InteractsWithSockets;

    public function __construct(public readonly string $userId) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'messages.unread';
    }

    /**
     * @return array{total: int}
     */
    public function broadcastWith(): array
    {
        return ['total' => app(ConversationInbox::class)->unreadTotal($this->userId)];
    }
}
