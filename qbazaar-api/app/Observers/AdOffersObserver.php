<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Services\Offers\OfferTransitionService;

/**
 * Closes the open offers of an ad that can no longer be bought: sold,
 * expired, blocked or deleted. A live ad sent back to review keeps its
 * offers, which simply cannot be accepted until it is active again.
 */
class AdOffersObserver
{
    private const array STATUSES_KEEPING_OFFERS = [AdStatus::ACTIVE, AdStatus::PENDING];

    public function __construct(private readonly OfferTransitionService $transitions) {}

    public function updated(Ad $ad): void
    {
        if ($ad->wasChanged('status') && ! in_array($ad->status, self::STATUSES_KEEPING_OFFERS, true)) {
            $this->transitions->expireOpenOffersOnAd($ad->id);
        }
    }

    public function deleted(Ad $ad): void
    {
        $this->transitions->expireOpenOffersOnAd($ad->id);
    }
}
