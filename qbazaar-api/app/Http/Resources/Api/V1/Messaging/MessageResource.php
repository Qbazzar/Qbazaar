<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Messaging;

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

        return [
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
                'avatar_thumb' => $sender->avatarThumbUrl(),
            ],
        ];
    }
}
