<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Messaging;

use App\Enums\MessageType;
use App\Http\Resources\Api\V1\Offers\OfferResource;
use App\Models\Message;
use App\Services\Media\MediaStorage;
use App\Services\Messaging\ChatMessageRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
            'body' => app(ChatMessageRenderer::class)->render($this->message_key, $this->params, $this->body),
            'message_key' => $this->message_key,
            'params' => $this->params,
            'type' => $this->type->value,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),

            'sender' => $sender === null ? null : [
                'id' => $sender->id,
                'full_name' => $sender->full_name,
                'avatar_thumb_url' => $sender->avatarThumbUrl(),
            ],
        ];

        if ($this->type === MessageType::IMAGE && $this->resource->relationLoaded('media')) {
            $image = $this->resource->image();
            $payload['media'] = $image === null ? null : $this->imagePayload($image);
        }

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

    /**
     * Both links expire; `url` is the downsized preview once the queued
     * conversion finished, the original until then.
     *
     * @return array<string, mixed>
     */
    private function imagePayload(Media $image): array
    {
        $storage = app(MediaStorage::class);

        return [
            'id' => $image->getKey(),
            'url' => $storage->signedConversionUrl($image, Message::IMAGE_PREVIEW),
            'original_url' => $storage->signedOriginalUrl($image),
            'mime_type' => $image->mime_type,
            'size_bytes' => (int) $image->size,
        ];
    }
}
