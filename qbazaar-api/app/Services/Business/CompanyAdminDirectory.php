<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Enums\AccountType;
use App\Enums\CompanyVerificationFilter;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Business accounts for the admin companies screen, whatever their status,
 * newest first. The verified / not verified filter runs on
 * users_business_verified_idx (account_type, business_verified_at). Each
 * page costs a fixed number of queries: the rows, the total and one eager
 * load per relation.
 */
class CompanyAdminDirectory
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function page(?CompanyVerificationFilter $verification, ?string $search): LengthAwarePaginator
    {
        $query = User::query()
            ->select('users.*')
            ->where('users.account_type', AccountType::BUSINESS->value)
            ->with(['businessProfile', 'avatarMedia', 'businessVerifier'])
            ->orderByDesc('users.created_at')
            ->orderByDesc('users.id');

        if ($verification === CompanyVerificationFilter::VERIFIED) {
            $query->whereNotNull('users.business_verified_at');
        } elseif ($verification === CompanyVerificationFilter::UNVERIFIED) {
            $query->whereNull('users.business_verified_at');
        }

        if ($search !== null && $search !== '') {
            $like = '%' . addcslashes($search, '\\%_') . '%';

            $query
                ->leftJoin('business_profiles', 'business_profiles.user_id', '=', 'users.id')
                ->where(static fn (Builder $match) => $match
                    ->where('business_profiles.business_name', 'like', $like)
                    ->orWhere('business_profiles.commercial_registration_number', 'like', $like)
                    ->orWhere('users.full_name', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhere('users.phone', 'like', $like));
        }

        return $query->paginate((int) config('qbazaar.business.admin_per_page'))->withQueryString();
    }
}
