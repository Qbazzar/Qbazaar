<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Messaging;

use App\Enums\MessageType;
use App\Http\Resources\Api\V1\Offers\OfferResource;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Single chat-line payload — used by transcript responses + the
 * `message.sent` broadcast payload.
 *
 * The sender is inlined as a tight mini-object (id / name / avatar)
 * rather than a full PublicUserResource because chat UI only ever
 * needs those three fields and we want to keep WebSocket frames small.
 *
 * For `type=offer` messages we eagerly attach the linked OfferResource
 * via the `offer` field so the FE can render the inline offer card
 * without a second round-trip. The relation is only serialised when it
 * has been explicitly loaded (Sprint 8 broadcasts don't load it; the
 * Sprint 9 transcript path does) — that keeps WebSocket payloads small
 * for non-offer chatter.
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sender = $this->resource->relationLoaded('sender')
            ? $this->resource->sender
            : null;

        $payload = [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'body' => $this->body,
            'type' => $this->type->value,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),

            'sender' => $sender === null ? null : [
                'id' => $sender->id,
                'full_name' => $sender->full_name,
                'avatar_thumb_url' => $sender->avatarThumbUrl(),
            ],
        ];

        // Only attach the offer envelope when the message actually is an
        // offer AND the relation was eager-loaded. Both gates matter:
        // skipping on type avoids serialising stray HasOne hits, skipping
        // on relationLoaded() prevents an N+1 on the inbox preview path.
        if ($this->type === MessageType::OFFER && $this->resource->relationLoaded('offer')) {
            $offer = $this->resource->offer;
            $payload['offer'] = $offer === null
                ? null
                : (new OfferResource($offer))->toArray($request);
        }

        return $payload;
    }
}
