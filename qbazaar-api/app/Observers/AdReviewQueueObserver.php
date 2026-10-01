<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Services\Ads\PendingReviewCounter;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Keeps the admin "waiting for review" counter in step with ad status changes.
 */
class AdReviewQueueObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly PendingReviewCounter $counter) {}

    public function created(Ad $ad): void
    {
        if ($ad->status === AdStatus::PENDING) {
            $this->counter->forget();
        }
    }

    public function updated(Ad $ad): void
    {
        if ($ad->wasChanged('status')) {
            $this->counter->forget();
        }
    }

    public function deleted(Ad $ad): void
    {
        $this->counter->forget();
    }

    public function restored(Ad $ad): void
    {
        $this->counter->forget();
    }
}
