<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle states for a buyer-side offer attached to a conversation.
 *
 * PENDING is the only "open" state — every other case is terminal and
 * permanently freezes the row (no further status mutations allowed).
 *
 *   - ACCEPTED  → seller agreed to the offered price.
 *   - REJECTED  → seller declined.
 *   - WITHDRAWN → buyer pulled the offer before the seller acted.
 *   - EXPIRED   → ExpireOldOffersJob flipped a stale pending offer.
 *
 * A buyer can only have ONE pending offer per ad at a time — the
 * `active-offer` invariant enforced by MakeOfferAction and the
 * (buyer_id, ad_id, status) composite index in the offers table.
 */
enum OfferStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';
    case EXPIRED = 'expired';

    /**
     * "Pending" is the only non-terminal status. Once an offer leaves
     * PENDING it can never come back — controllers and actions rely on
     * this invariant to keep their guards simple.
     */
    public function isTerminal(): bool
    {
        return $this !== self::PENDING;
    }
}
