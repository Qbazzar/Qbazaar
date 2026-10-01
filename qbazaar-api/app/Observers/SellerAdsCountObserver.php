<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;

/**
 * Keeps `users.active_ads_count` in step with the seller's ACTIVE ads. Every
 * status change goes through a model save, so the counter moves with the
 * same write that changed the ad and inside its transaction.
 */
class SellerAdsCountObserver
{
    public function created(Ad $ad): void
    {
        if ($ad->status === AdStatus::ACTIVE) {
            $this->adjust($ad, 1);
        }
    }

    public function updated(Ad $ad): void
    {
        if (! $ad->wasChanged('status')) {
            return;
        }

        $wasActive = $this->isActive($ad->getOriginal('status'));
        $isActive = $ad->status === AdStatus::ACTIVE;

        if ($wasActive !== $isActive) {
            $this->adjust($ad, $isActive ? 1 : -1);
        }
    }

    public function deleted(Ad $ad): void
    {
        // Force-deleting an already trashed ad must not count it out twice.
        $wasLive = ! $ad->isForceDeleting() || $ad->deleted_at === null;

        if ($wasLive && $ad->status === AdStatus::ACTIVE) {
            $this->adjust($ad, -1);
        }
    }

    public function restored(Ad $ad): void
    {
        if ($ad->status === AdStatus::ACTIVE) {
            $this->adjust($ad, 1);
        }
    }

    private function isActive(mixed $status): bool
    {
        return $status === AdStatus::ACTIVE || $status === AdStatus::ACTIVE->value;
    }

    private function adjust(Ad $ad, int $delta): void
    {
        $seller = User::query()->whereKey($ad->user_id)->toBase();

        if ($delta > 0) {
            $seller->increment('active_ads_count', $delta);

            return;
        }

        $seller->where('active_ads_count', '>', 0)->decrement('active_ads_count', -$delta);
    }
}
