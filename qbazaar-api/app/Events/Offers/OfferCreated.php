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
 * Fired right after MakeOfferAction commits.
 *
 * Broadcasts on TWO private channels — same pattern as the messaging
 * events (Sprint 8) so the FE can lean on a single subscription model:
 *   1. `conversation.{conversationId}` — both participants get the new
 *      offer card inline with the chat transcript.
 *   2. `user.{otherUserId}` — the seller's session pushes a header /
 *      inbox badge update even when the conversation isn't currently
 *      open.
 *
 * The payload is the full {@see OfferResource} envelope + a thin
 * `{conversation_id}` echo so subscribers can route the frame without
 * decoding the resource.
 */
class OfferCreated implements ShouldBroadcast
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
        return 'offer.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Synthetic request — OfferResource only reads `user()` from it,
        // which is correctly null in the broadcast path so subscribers
        // re-derive `viewer_role` on the client.
        $request = Request::create('/internal/broadcast', 'GET');

        return [
            'offer' => (new OfferResource($this->offer))->toArray($request),
            'conversation_id' => $this->offer->conversation_id,
        ];
    }
}
