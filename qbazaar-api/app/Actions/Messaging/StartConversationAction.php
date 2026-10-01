<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\User;

/**
 * Resolves the conversation row for (buyer, ad) — creating it on first
 * contact, returning the existing one on every subsequent call.
 *
 * Business rules:
 *  - MSG_CONVERSATION_OWN_AD (422): the ad owner cannot start a
 *    conversation on their own listing.
 *  - MSG_BLOCKED (403): refuse when either side has blocked the other
 *    (we re-check this on send too, but failing fast here prevents
 *    creating a row only to dead-end on the first message).
 *  - AD_NOT_ACTIVE (422): a new conversation needs a live ad from an
 *    active seller. An existing thread is still returned so the buyer keeps
 *    access to its history after the ad is sold or hidden.
 *  - MSG_CHAT_DISABLED (403): the seller has turned chat off.
 *
 * Caller receives the conversation with `ad.user` eager-loaded so the
 * response resource doesn't need a follow-up query.
 *
 * Returns a tuple-like associative array so the controller can tell new
 * vs. existing apart (status 201 vs. 200) without a second DB lookup.
 */
class StartConversationAction
{
    /**
     * @return array{conversation: Conversation, created: bool}
     */
    public function execute(User $buyer, Ad $ad): array
    {
        if ($ad->user_id === $buyer->id) {
            throw new DomainException(ErrorCode::MSG_CONVERSATION_OWN_AD);
        }

        // Hydrate seller once; we'll need it for the block check anyway
        // and for the eventual resource serialisation.
        $ad->loadMissing('user');

        /** @var User $seller */
        $seller = $ad->user;

        if ($buyer->hasBlocked($seller) || $seller->hasBlocked($buyer)) {
            throw new DomainException(ErrorCode::MSG_BLOCKED);
        }

        $existing = Conversation::query()
            ->where('ad_id', $ad->id)
            ->where('buyer_id', $buyer->id)
            ->first();

        if ($existing !== null) {
            $existing->load(['ad.user', 'ad.primaryImage', 'buyer', 'seller']);

            return ['conversation' => $existing, 'created' => false];
        }

        if (! $ad->isPubliclyListed()) {
            throw new DomainException(ErrorCode::AD_NOT_ACTIVE);
        }

        if (! $seller->privacySettings()->allow_chat) {
            throw new DomainException(ErrorCode::MSG_CHAT_DISABLED);
        }

        $conversation = Conversation::query()->create([
            'ad_id' => $ad->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);

        $conversation->load(['ad.user', 'ad.primaryImage', 'buyer', 'seller']);

        return ['conversation' => $conversation, 'created' => true];
    }
}
