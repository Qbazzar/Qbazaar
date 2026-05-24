<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Messaging;

use App\Http\Resources\Api\V1\Ads\AdSummaryResource;
use App\Http\Resources\Api\V1\Users\PublicUserResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full conversation payload — used by `store` + `show`. Returns the ad
 * summary card, both participants in mini form, the denormalised preview
 * fields, and the caller-relative `unread_count`.
 *
 * `unread_count` is computed on serialise against `$request->user()` so the
 * same row can produce different counts for buyer vs. seller without
 * touching the database column.
 *
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
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

        return [
            'id' => $this->id,
            'ad' => $this->whenLoaded(
                'ad',
                fn () => (new AdSummaryResource($this->resource->ad))->toArray($request),
            ),
            'buyer_id' => $this->buyer_id,
            'seller_id' => $this->seller_id,
            'other_participant' => $other === null
                ? null
                : (new PublicUserResource($other))->toArray($request),
            'last_message_preview' => $this->last_message_preview,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread_count' => $caller !== null
                ? $this->resource->unreadCountFor($caller)
                : 0,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
