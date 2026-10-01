<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Enums\AdStatus;
use App\Enums\PlatformSetting;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\User;
use App\Services\Ads\AdLifecycleService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;

/**
 * A seller submits a draft (or a fixed rejected ad) for review.
 *
 * The seller row is locked so two concurrent publishes cannot both slip
 * under the daily limit. Publishing an ad that is already waiting for
 * review changes nothing and does not notify the reviewers again.
 */
class PublishAdAction
{
    public function __construct(
        private readonly AdLifecycleService $lifecycle,
        private readonly SettingsService $settings,
    ) {}

    public function __invoke(Ad $ad, User $seller): Ad
    {
        return DB::transaction(function () use ($ad, $seller): Ad {
            User::query()->lockForUpdate()->findOrFail($seller->getKey());

            /** @var Ad $locked */
            $locked = Ad::query()->lockForUpdate()->findOrFail($ad->getKey());

            if ($locked->status === AdStatus::PENDING) {
                return $locked;
            }

            $this->ensureHasEnoughImages($locked);
            $this->ensureWithinDailyLimit($locked, $seller);

            return $this->lifecycle->submitForReview($locked);
        });
    }

    private function ensureHasEnoughImages(Ad $ad): void
    {
        $minimum = (int) config('qbazaar.ads.min_images');
        $count = $ad->media()->where('collection_name', 'images')->count();

        if ($count < $minimum) {
            throw new DomainException(
                ErrorCode::AD_IMAGES_REQUIRED,
                details: ['min' => $minimum, 'current' => $count],
            );
        }
    }

    private function ensureWithinDailyLimit(Ad $ad, User $seller): void
    {
        $limit = $this->settings->integer(PlatformSetting::AD_DAILY_PUBLISH_LIMIT);

        $submittedToday = Ad::query()
            ->forUser($seller)
            ->whereKeyNot($ad->getKey())
            ->where('submitted_at', '>=', now()->subDay())
            ->count();

        if ($submittedToday >= $limit) {
            throw new DomainException(
                ErrorCode::AD_DAILY_PUBLISH_LIMIT,
                details: ['limit' => $limit],
            );
        }
    }
}
