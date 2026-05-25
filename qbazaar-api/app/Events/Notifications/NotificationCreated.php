<?php

declare(strict_types=1);

namespace App\Events\Notifications;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time ping fired the moment a database-channel notification is
 * persisted for a user.
 *
 * Drives the FE notification bell's "unseen" dot without polling. The
 * frontend subscribes to its own `user.{userId}` private channel
 * (declared in routes/channels.php) and listens for `notification.created`
 * to invalidate the unread-count + list queries.
 *
 * Implements `ShouldBroadcastAfterCommit` because the listener fires inside
 * Laravel's `NotificationSent` event — which runs immediately after the
 * notification row is inserted, but BEFORE the surrounding transaction (if
 * any) commits. Broadcasting before commit would race a transaction
 * rollback, leading to clients receiving pings for rows that never existed.
 */
class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $userId,
        public string $notificationId,
        public string $type,
        public array $data,
        public string $createdAt,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notificationId,
            'type' => $this->type,
            'data' => $this->data,
            'created_at' => $this->createdAt,
        ];
    }
}
