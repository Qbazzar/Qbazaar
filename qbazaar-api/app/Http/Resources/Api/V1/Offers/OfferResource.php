<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Offers;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Single Offer payload — used by Offers endpoint responses, the linked
 * `offer` field on MessageResource, and the broadcastWith() payload of
 * every `OfferCreated|Accepted|Rejected|Withdrawn|Expired` event.
 *
 * The shape stays flat (no nested user/ad envelopes) so the FE can keep
 * the offer-card component cheap — the conversation page already has the
 * buyer / seller / ad context cached from the conversation fetch.
 *
 * `viewer_role` is computed from the request user so the FE can branch
 * UI ("accept/reject" buttons vs "withdraw") without re-deriving the
 * relationship client-side. It's null when the offer is rendered from
 * a context with no authenticated user (e.g. the broadcastWith() path,
 * see {@see resolveViewerRole()}).
 *
 * @mixin Offer
 */
class OfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'ad_id' => $this->ad_id,
            'buyer_id' => $this->buyer_id,
            'seller_id' => $this->seller_id,
            'message_id' => $this->message_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'note' => $this->note,
            'status' => $this->status->value,
            'expires_at' => $this->expires_at->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'withdrawn_at' => $this->withdrawn_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'viewer_role' => $this->resolveViewerRole($request),
        ];
    }

    /**
     * `'buyer' | 'seller' | null` — null when there's no authenticated
     * user or the user isn't a participant. The broadcast-time payload
     * always lands here as `null` since broadcasts construct a synthetic
     * Request with no user; that's intentional — clients re-derive the
     * role from their session.
     */
    private function resolveViewerRole(Request $request): ?string
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return match ($user->id) {
            $this->buyer_id => 'buyer',
            $this->seller_id => 'seller',
            default => null,
        };
    }
}
