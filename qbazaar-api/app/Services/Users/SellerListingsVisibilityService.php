<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Keeps a seller's live ads in the search index in step with the seller's
 * account status. Ad statuses are never touched, so an admin-blocked or sold
 * ad stays that way after the seller is reactivated; database listings rely
 * on {@see Ad::scopePubliclyListed()} instead.
 */
class SellerListingsVisibilityService
{
    public function hide(User $seller): void
    {
        $this->activeAdsOf($seller)->chunkById(
            (int) config('scout.chunk.unsearchable', 500),
            function (Collection $ads): void {
                $ads->first()?->queueRemoveFromSearch($ads);
            },
        );
    }

    public function restore(User $seller): void
    {
        $this->activeAdsOf($seller)->with('user')->chunkById(
            (int) config('scout.chunk.searchable', 500),
            function (Collection $ads): void {
                $listed = $ads->filter(fn (Ad $ad): bool => $ad->shouldBeSearchable());
                $listed->first()?->queueMakeSearchable($listed);
            },
        );
    }

    /**
     * @return Builder<Ad>
     */
    private function activeAdsOf(User $seller): Builder
    {
        return Ad::query()->forUser($seller)->active();
    }
}
