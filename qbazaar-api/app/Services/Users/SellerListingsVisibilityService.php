<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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
        $this->activeAdsOf($seller)->unsearchable();
    }

    public function restore(User $seller): void
    {
        $this->activeAdsOf($seller)->with('user')->searchable();
    }

    /**
     * @return Builder<Ad>
     */
    private function activeAdsOf(User $seller): Builder
    {
        return Ad::query()->forUser($seller)->active();
    }
}
