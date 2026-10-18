<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Business\FeaturedCompanies;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Active business accounts: the companies staff feature first, in their
 * order, then the rest most followed first.
 *
 * Without a search the featured block (capped, read on
 * business_profiles_featured_idx) is laid in front of the main list, which
 * keeps running on users_directory_idx without a join; pages are cut across
 * both as if they were one list. A search matches the business name or the
 * account name and sorts the featured matches first within that already
 * narrow set.
 */
class CompanyDirectory
{
    public function __construct(
        private readonly FeaturedCompanies $featured,
    ) {}

    /** @return LengthAwarePaginatorContract<int, User> */
    public function search(?string $term): LengthAwarePaginatorContract
    {
        $perPage = (int) config('qbazaar.social.companies_per_page');

        if ($term !== null && $term !== '') {
            return $this->matching($term, $perPage);
        }

        return $this->featuredFirst($perPage);
    }

    /** @return LengthAwarePaginatorContract<int, User> */
    private function featuredFirst(int $perPage): LengthAwarePaginatorContract
    {
        $page = Paginator::resolveCurrentPage();
        $offset = ($page - 1) * $perPage;

        $featured = $this->featured->listed();
        $lead = $featured->slice($offset, $perPage)->values();

        $rest = $this->listedQuery()->whereNotIn('users.id', $featured->modelKeys());
        $restTotal = $rest->toBase()->getCountForPagination();
        $restLimit = $perPage - $lead->count();

        $rows = $restLimit > 0
            ? $rest->orderByDesc('users.followers_count')
                ->orderByDesc('users.id')
                ->offset(max(0, $offset - $featured->count()))
                ->limit($restLimit)
                ->get()
            : new Collection;

        /** @var Collection<int, User> $items */
        $items = $lead->concat($rows);
        $this->loadCardRelations($items);

        return new LengthAwarePaginator($items, $featured->count() + $restTotal, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    /** @return LengthAwarePaginatorContract<int, User> */
    private function matching(string $term, int $perPage): LengthAwarePaginatorContract
    {
        $like = '%' . addcslashes($term, '\\%_') . '%';

        $page = $this->listedQuery()
            ->leftJoin('business_profiles', 'business_profiles.user_id', '=', 'users.id')
            ->where(static fn (Builder $match) => $match
                ->where('business_profiles.business_name', 'like', $like)
                ->orWhere('users.full_name', 'like', $like))
            ->orderByRaw('CASE WHEN business_profiles.featured_position IS NULL THEN 1 ELSE 0 END')
            ->orderBy('business_profiles.featured_position')
            ->orderByDesc('users.followers_count')
            ->orderByDesc('users.id')
            ->paginate($perPage);

        /** @var Collection<int, User> $items */
        $items = $page->getCollection();
        $this->loadCardRelations($items);

        return $page;
    }

    /** @return Builder<User> */
    private function listedQuery(): Builder
    {
        return User::query()
            ->select('users.*')
            ->where('users.account_type', AccountType::BUSINESS->value)
            ->where('users.status', UserStatus::ACTIVE->value);
    }

    /**
     * @param Collection<int, User> $companies
     */
    private function loadCardRelations(Collection $companies): void
    {
        $companies->load([
            'businessProfile',
            'media' => static fn ($media) => $media->where('collection_name', User::BUSINESS_COVER_COLLECTION),
        ]);
    }
}
