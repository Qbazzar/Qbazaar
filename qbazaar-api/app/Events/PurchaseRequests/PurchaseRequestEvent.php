<?php

declare(strict_types=1);

namespace App\Events\PurchaseRequests;

use App\Events\Concerns\BroadcastsOnRealtimeQueue;
use App\Http\Resources\Api\V1\PurchaseRequests\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * A purchase request changed. Broadcast like the offer events: on the
 * conversation, so an open chat flips the card, and on the other side's
 * user channel, so their inbox updates when the chat is closed. Every
 * subclass is dispatched after the commit.
 */
abstract class PurchaseRequestEvent implements ShouldBroadcast
{
    use BroadcastsOnRealtimeQueue, Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PurchaseRequest $purchaseRequest,
        public string $otherUserId,
    ) {}

    abstract public function broadcastAs(): string;

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->purchaseRequest->conversation_id),
            new PrivateChannel('user.' . $this->otherUserId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // No user on this request, so viewer_role is null and clients derive it.
        $request = Request::create('/internal/broadcast', 'GET');

        return [
            'purchase_request' => (new PurchaseRequestResource($this->purchaseRequest))->toArray($request),
            'conversation_id' => $this->purchaseRequest->conversation_id,
        ];
    }
}
