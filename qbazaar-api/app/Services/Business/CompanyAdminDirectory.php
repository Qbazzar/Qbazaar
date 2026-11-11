<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Enums\AccountType;
use App\Enums\CompanyVerificationFilter;
use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Business accounts for the admin companies screen, whatever their status,
 * newest first. The verified / not verified filter runs on
 * users_business_verified_idx (account_type, business_verified_at). Each
 * page costs a fixed number of queries: the rows, the total and one eager
 * load per relation.
 *
 * A search shorter than the configured minimum is ignored. Identifiers are
 * matched from their first character, so a typed email, phone or CR number
 * reads one range of its own index. A name may match anywhere in it, but
 * only among business accounts, which the account_type indexes already
 * narrow the scan to.
 */
class CompanyAdminDirectory
{
    /** A phone or CR number as staff type it: digits, an optional leading "+", spaces. */
    private const string NUMBER_PATTERN = '/^\+?\d[\d ]*$/';

    private const string QATAR_PREFIX = '+974';

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

        if ($search !== null && mb_strlen($search) >= $this->minSearchLength()) {
            $this->match($query, $search);
        }

        return $query->paginate((int) config('qbazaar.business.admin_per_page'))->withQueryString();
    }

    public function minSearchLength(): int
    {
        return (int) config('qbazaar.business.admin_search_min_length');
    }

    /**
     * @param Builder<User> $query
     */
    private function match(Builder $query, string $term): void
    {
        $prefix = $this->escape($term) . '%';

        if (str_contains($term, '@')) {
            $query->where('users.email', 'like', $prefix);

            return;
        }

        if (preg_match(self::NUMBER_PATTERN, $term) === 1) {
            $digits = str_replace(' ', '', ltrim($term, '+'));

            $query->where(fn (Builder $match) => $match
                ->whereIn('users.id', $this->phonesStartingWith($digits))
                ->orWhereIn('users.id', $this->registrationsStartingWith($digits)));

            return;
        }

        $anywhere = '%' . $this->escape($term) . '%';

        $query->where(fn (Builder $match) => $match
            ->where('users.full_name', 'like', $anywhere)
            ->orWhere('users.email', 'like', $prefix)
            ->orWhereIn('users.id', BusinessProfile::query()->select('user_id')->where('business_name', 'like', $anywhere))
            ->orWhereIn('users.id', $this->registrationsStartingWith($term)));
    }

    /**
     * Phones are stored as +974XXXXXXXX: the digits match either after the
     * "+" (typed with the country code) or after +974 (typed locally).
     *
     * @return Builder<User>
     */
    private function phonesStartingWith(string $digits): Builder
    {
        return User::query()
            ->select('id')
            ->where(fn (Builder $phone) => $phone
                ->where('phone', 'like', '+' . $digits . '%')
                ->orWhere('phone', 'like', self::QATAR_PREFIX . $digits . '%'));
    }

    /**
     * @return Builder<BusinessProfile>
     */
    private function registrationsStartingWith(string $term): Builder
    {
        return BusinessProfile::query()
            ->select('user_id')
            ->where('commercial_registration_number', 'like', $this->escape($term) . '%');
    }

    private function escape(string $term): string
    {
        return addcslashes($term, '\\%_');
    }
}
