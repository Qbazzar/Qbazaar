<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Messaging;

use App\Models\Ad;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Lean inbox row — used by `GET /conversations`.
 *
 * Smaller than {@see ConversationResource}: we only ship enough ad context
 * to render a chat card (thumbnail + title), the other participant's name
 * + avatar, the last preview, and the unread count. The full ad detail is
 * one round-trip away (`GET /conversations/{id}`).
 *
 * @mixin Conversation
 */
class ConversationListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $caller */
        $caller = $request->user();

        $other = $caller !== null && $this->resource->isParticipant($caller)
            ? $this->resource->otherParticipant($caller)
            : null;

        $ad = $this->resource->ad;
        $primary = $ad instanceof Ad && $ad->relationLoaded('media')
            ? $ad->getMedia('images')->sortBy('order_column')->first()
            : null;

        return [
            'id' => $this->id,
            'ad' => $ad === null ? null : [
                'id' => $ad->id,
                'title' => $ad->title,
                'thumb_url' => $primary instanceof Media
                    ? ($primary->hasGeneratedConversion('thumbnail')
                        ? $primary->getUrl('thumbnail')
                        : $primary->getUrl())
                    : null,
            ],
            'other_participant' => $other === null ? null : [
                'id' => $other->id,
                'full_name' => $other->full_name,
                'avatar_thumb' => $other->avatarThumbUrl(),
            ],
            'last_message_preview' => $this->last_message_preview,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread_count' => $caller !== null
                ? $this->resource->unreadCountFor($caller)
                : 0,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
