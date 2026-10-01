<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;

/**
 * Participant rules for the offer lifecycle endpoints: the responder may
 * accept, reject or counter, the proposer may withdraw. Mutability and
 * ad-status checks live in the actions, which run them under a lock.
 */
class OfferPolicy
{
    public function view(User $user, Offer $offer): bool
    {
        return $user->id === $offer->buyer_id || $user->id === $offer->seller_id;
    }

    public function accept(User $user, Offer $offer): bool
    {
        return $this->isOpenFor($user, $offer->responderId(), $offer);
    }

    public function reject(User $user, Offer $offer): bool
    {
        return $this->isOpenFor($user, $offer->responderId(), $offer);
    }

    public function counter(User $user, Offer $offer): bool
    {
        return $this->isOpenFor($user, $offer->responderId(), $offer);
    }

    public function withdraw(User $user, Offer $offer): bool
    {
        return $this->isOpenFor($user, $offer->proposerId(), $offer);
    }

    private function isOpenFor(User $user, string $allowedUserId, Offer $offer): bool
    {
        return $user->id === $allowedUserId && $offer->status === OfferStatus::PENDING;
    }
}
