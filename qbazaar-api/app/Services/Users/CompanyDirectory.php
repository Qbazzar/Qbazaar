<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\AccountType;
use App\Enums\AdStatus;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Active business accounts, most followed first. The filter and the sort run
 * on users_directory_idx; the optional search matches the business name or
 * the account name within that already narrow set.
 */
class CompanyDirectory
{
    /** @return LengthAwarePaginator<int, User> */
    public function search(?string $term): LengthAwarePaginator
    {
        $query = User::query()
            ->select('users.*')
            ->where('users.account_type', AccountType::BUSINESS->value)
            ->where('users.status', UserStatus::ACTIVE->value)
            ->with([
                'businessProfile',
                'media' => static fn ($media) => $media->where('collection_name', User::BUSINESS_COVER_COLLECTION),
            ])
            ->withCount(['ads as active_ads_count' => static fn (Builder $ads) => $ads->where('status', AdStatus::ACTIVE->value)])
            ->orderByDesc('users.followers_count')
            ->orderByDesc('users.id');

        if ($term !== null && $term !== '') {
            $like = '%' . addcslashes($term, '\\%_') . '%';

            $query
                ->leftJoin('business_profiles', 'business_profiles.user_id', '=', 'users.id')
                ->where(static fn (Builder $match) => $match
                    ->where('business_profiles.business_name', 'like', $like)
                    ->orWhere('users.full_name', 'like', $like));
        }

        return $query->paginate((int) config('qbazaar.social.companies_per_page'));
    }
}
