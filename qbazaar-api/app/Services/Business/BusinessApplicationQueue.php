<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Enums\BusinessApplicationStatus;
use App\Models\BusinessApplication;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The staff review queue of business applications. Open statuses are served
 * first in, first out on business_applications_queue_idx (status,
 * submitted_at); decided ones newest first. Each page costs a fixed number
 * of queries: the rows, the total, and one eager load per relation.
 */
class BusinessApplicationQueue
{
    public const string PENDING_COUNT_KEY = 'admin:business-applications:pending-count';

    public function __construct(
        private readonly Cache $cache,
    ) {}

    /**
     * @return LengthAwarePaginator<int, BusinessApplication>
     */
    public function page(BusinessApplicationStatus $status, ?string $search): LengthAwarePaginator
    {
        $query = BusinessApplication::query()
            ->where('status', $status->value)
            ->with(['user.avatarMedia', 'reviewer'])
            ->withCount('media');

        if ($status->isOpen()) {
            $query->orderBy('submitted_at')->orderBy('id');
        } else {
            $query->orderByDesc('submitted_at')->orderByDesc('id');
        }

        if ($search !== null && $search !== '') {
            $like = '%' . addcslashes($search, '\\%_') . '%';

            $query->where(static fn (Builder $match) => $match
                ->where('business_name', 'like', $like)
                ->orWhere('legal_name', 'like', $like)
                ->orWhere('commercial_registration_number', 'like', $like));
        }

        return $query->paginate((int) config('qbazaar.business.admin_per_page'))->withQueryString();
    }

    /**
     * A user's applications, newest first, for the company page. Bounded:
     * a user can only open a new one after the previous was decided.
     *
     * @return Collection<int, BusinessApplication>
     */
    public function historyOf(User $user, int $limit = 10): Collection
    {
        return BusinessApplication::query()
            ->where('user_id', $user->id)
            ->with('reviewer')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * The user's most recent application with its documents, for the
     * applicant's own screen. Two queries on business_applications_user_idx.
     */
    public function latestOf(User $user): ?BusinessApplication
    {
        return BusinessApplication::query()
            ->where('user_id', $user->id)
            ->with('media')
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    /**
     * Applications waiting for staff, shown on every admin page. One shared
     * key, forgotten on every submit and decision; the TTL only bounds drift.
     */
    public function pendingCount(): int
    {
        return (int) $this->cache->remember(
            self::PENDING_COUNT_KEY,
            (int) config('qbazaar.admin.pending_review_count_cache_seconds'),
            static fn (): int => BusinessApplication::query()->where('status', BusinessApplicationStatus::PENDING->value)->count(),
        );
    }

    public function forgetPendingCount(): void
    {
        $this->cache->forget(self::PENDING_COUNT_KEY);
    }
}
