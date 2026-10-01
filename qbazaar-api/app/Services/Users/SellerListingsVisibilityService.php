<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Keeps a seller's ads in step with the seller's account status: the stored
 * `seller_active` flag that {@see Ad::scopePubliclyListed()} filters on, and
 * the search index. Ad statuses are never touched, so an admin-blocked or
 * sold ad stays that way after the seller is reactivated.
 */
class SellerListingsVisibilityService
{
    public function hide(User $seller): void
    {
        $this->markSellerActive($seller, false);

        $this->activeAdsOf($seller)->chunkById(
            (int) config('scout.chunk.unsearchable', 500),
            function (Collection $ads): void {
                $ads->first()?->queueRemoveFromSearch($ads);
            },
        );
    }

    public function restore(User $seller): void
    {
        $this->markSellerActive($seller, true);

        $this->activeAdsOf($seller)->with('user')->chunkById(
            (int) config('scout.chunk.searchable', 500),
            function (Collection $ads): void {
                $listed = $ads->filter(fn (Ad $ad): bool => $ad->shouldBeSearchable());
                $listed->first()?->queueMakeSearchable($listed);
            },
        );
    }

    /**
     * One statement on ads_user_status_published_idx. It skips model events and
     * updated_at on purpose: the ads themselves did not change.
     */
    private function markSellerActive(User $seller, bool $active): void
    {
        Ad::withTrashed()->where('user_id', $seller->id)->toBase()->update(['seller_active' => $active]);
    }

    /**
     * @return Builder<Ad>
     */
    private function activeAdsOf(User $seller): Builder
    {
        return Ad::query()->forUser($seller)->active();
    }
}
