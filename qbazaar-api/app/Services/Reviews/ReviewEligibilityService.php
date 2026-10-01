<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\OfferStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Offer;
use App\Models\Review;
use App\Models\User;

/**
 * A buyer may review a seller once per ad, and only after a closed deal,
 * proven by an accepted offer on that ad.
 */
class ReviewEligibilityService
{
    /**
     * @throws DomainException
     */
    public function ensureCanReview(User $reviewer, Ad $ad): void
    {
        if ($reviewer->id === $ad->user_id) {
            throw new DomainException(ErrorCode::REVIEW_OWN_AD);
        }

        if (! $this->hasClosedDeal($reviewer, $ad)) {
            throw new DomainException(ErrorCode::REVIEW_NOT_ELIGIBLE);
        }

        if ($this->hasReviewed($reviewer, $ad)) {
            throw new DomainException(ErrorCode::REVIEW_ALREADY_EXISTS);
        }
    }

    private function hasClosedDeal(User $reviewer, Ad $ad): bool
    {
        return Offer::query()
            ->where('ad_id', $ad->id)
            ->where('buyer_id', $reviewer->id)
            ->where('status', OfferStatus::ACCEPTED->value)
            ->exists();
    }

    private function hasReviewed(User $reviewer, Ad $ad): bool
    {
        return Review::query()
            ->where('reviewer_id', $reviewer->id)
            ->where('ad_id', $ad->id)
            ->exists();
    }
}
