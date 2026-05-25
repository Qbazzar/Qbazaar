<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;

/**
 * Authorization rules for the offer lifecycle endpoints.
 *
 * The richer "is the offer still mutable?" + ad-status checks live in
 * the actions themselves (with dedicated ErrorCodes — OFFER_NOT_PENDING,
 * etc). The policy here only enforces participant rules so the
 * AuthorizationException renderer can surface a plain 403 FORBIDDEN for
 * out-of-band callers (e.g. someone trying to accept an offer that's
 * not theirs to act on).
 */
class OfferPolicy
{
    public function view(User $user, Offer $offer): bool
    {
        return $this->isParticipant($user, $offer);
    }

    public function accept(User $user, Offer $offer): bool
    {
        return $user->id === $offer->seller_id
            && $offer->status === OfferStatus::PENDING;
    }

    public function reject(User $user, Offer $offer): bool
    {
        return $user->id === $offer->seller_id
            && $offer->status === OfferStatus::PENDING;
    }

    public function withdraw(User $user, Offer $offer): bool
    {
        return $user->id === $offer->buyer_id
            && $offer->status === OfferStatus::PENDING;
    }

    private function isParticipant(User $user, Offer $offer): bool
    {
        return $user->id === $offer->buyer_id || $user->id === $offer->seller_id;
    }
}
