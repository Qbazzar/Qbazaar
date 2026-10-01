<?php

declare(strict_types=1);

namespace App\Events\Offers;

use App\Events\Concerns\BroadcastsOnRealtimeQueue;
use App\Http\Resources\Api\V1\Offers\OfferResource;
use App\Models\Offer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after CounterOfferAction commits, carrying the new counter-offer;
 * its `parent_offer_id` names the offer it answers. See {@see OfferCreated} for
 * channel routing notes.
 */
class OfferCountered implements ShouldBroadcast
{
    use BroadcastsOnRealtimeQueue, Dispatchable, InteractsWithSockets, SerializesModels;

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
        return 'offer.countered';
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
