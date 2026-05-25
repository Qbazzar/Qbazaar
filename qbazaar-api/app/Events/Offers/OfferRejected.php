<?php

declare(strict_types=1);

namespace App\Events\Offers;

use App\Http\Resources\Api\V1\Offers\OfferResource;
use App\Models\Offer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after RejectOfferAction commits. See {@see OfferCreated} for the
 * channel-routing rationale (mirrors the messaging event design).
 */
class OfferRejected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $otherUserId;

    public function __construct(
        public Offer $offer,
        string $otherUserId,
    ) {
        $this->otherUserId = $otherUserId;
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->offer->conversation_id),
            new PrivateChannel('user.' . $this->otherUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'offer.rejected';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $request = Request::create('/internal/broadcast', 'GET');

        return [
            'offer' => (new OfferResource($this->offer))->toArray($request),
            'conversation_id' => $this->offer->conversation_id,
        ];
    }
}
