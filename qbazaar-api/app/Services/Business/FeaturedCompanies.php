<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Companies staff pin to the top of the companies directory and the home
 * screen, in the order staff give them. The list is capped
 * (qbazaar.business.featured_max), and every read walks
 * business_profiles_featured_idx, so it only touches the featured rows.
 * Staff change the list through {@see FeaturedCompanyCurator}.
 */
class FeaturedCompanies
{
    /**
     * Featured companies the public may see (active business accounts), in
     * staff order. One query; callers eager-load what they render.
     *
     * @return Collection<int, User>
     */
    public function listed(): Collection
    {
        return $this->featuredQuery()
            ->where('users.account_type', AccountType::BUSINESS->value)
            ->where('users.status', UserStatus::ACTIVE->value)
            ->get();
    }

    /**
     * Every featured company whatever its status, so staff also see the ones
     * a suspension currently hides.
     *
     * @return Collection<int, User>
     */
    public function forAdmin(): Collection
    {
        return $this->featuredQuery()
            ->with(['businessProfile', 'avatarMedia'])
            ->get();
    }

    public function max(): int
    {
        return (int) config('qbazaar.business.featured_max');
    }

    /**
     * @return Builder<User>
     */
    private function featuredQuery(): Builder
    {
        return User::query()
            ->select('users.*')
            ->join('business_profiles', 'business_profiles.user_id', '=', 'users.id')
            ->whereNotNull('business_profiles.featured_position')
            ->orderBy('business_profiles.featured_position')
            ->orderBy('users.id')
            ->limit($this->max());
    }
}
