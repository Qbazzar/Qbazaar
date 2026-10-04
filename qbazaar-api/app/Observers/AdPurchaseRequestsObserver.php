<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Services\PurchaseRequests\PurchaseRequestTransitionService;

/**
 * Cancels the pending "Buy Now" requests of an ad that can no longer be
 * bought (sold, expired, blocked or deleted), the same moments the open
 * offers on it expire.
 */
class AdPurchaseRequestsObserver
{
    private const array STATUSES_KEEPING_REQUESTS = [AdStatus::ACTIVE, AdStatus::PENDING];

    public function __construct(private readonly PurchaseRequestTransitionService $transitions) {}

    public function updated(Ad $ad): void
    {
        if ($ad->wasChanged('status') && ! in_array($ad->status, self::STATUSES_KEEPING_REQUESTS, true)) {
            $this->transitions->cancelOpenRequestsOnAd($ad->id);
        }
    }

    public function deleted(Ad $ad): void
    {
        $this->transitions->cancelOpenRequestsOnAd($ad->id);
    }
}
