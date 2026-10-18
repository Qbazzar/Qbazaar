<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\BusinessProfile;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Catalog\CatalogCache;
use App\Support\ManualOrder;
use App\Support\Sql\PositionUpdate;
use Illuminate\Support\Facades\DB;

/**
 * Staff add, remove and reorder featured companies. Every change runs on
 * the locked featured rows, is written to the admin audit log and drops the
 * cached home feed, which shows the featured companies first. Featuring a
 * featured company (or removing one that is not) is refused, so a stale
 * page never writes a change that did not happen.
 */
class FeaturedCompanyCurator
{
    public function __construct(
        private readonly FeaturedCompanies $featured,
        private readonly AdminAuditLogger $audit,
        private readonly CatalogCache $catalog,
    ) {}

    public function feature(User $admin, User $company): void
    {
        if (! $company->isBusiness()) {
            throw new DomainException(ErrorCode::BUSINESS_ACCOUNT_REQUIRED);
        }

        $position = DB::transaction(function () use ($company): int {
            $company->businessProfile()->firstOrCreate();

            // Locking the featured rows serialises concurrent features, so the
            // cap and the next position are read from a stable list.
            $taken = BusinessProfile::query()
                ->whereNotNull('featured_position')
                ->lockForUpdate()
                ->pluck('featured_position', 'user_id');

            if ($taken->has($company->id)) {
                throw new DomainException(ErrorCode::BUSINESS_ALREADY_FEATURED);
            }

            if ($taken->count() >= $this->featured->max()) {
                throw new DomainException(ErrorCode::BUSINESS_FEATURED_LIMIT);
            }

            $next = (int) $taken->max() + 1;
            BusinessProfile::query()->whereKey($company->id)->update(['featured_position' => $next]);

            return $next;
        });

        $this->audit->record($admin, 'admin.companies.feature', $company, ['position' => $position]);
        $this->catalog->homeCurationChanged();
    }

    public function unfeature(User $admin, User $company): void
    {
        DB::transaction(function () use ($company): void {
            $order = $this->lockedOrder();

            if (! in_array($company->id, $order, true)) {
                throw new DomainException(ErrorCode::BUSINESS_NOT_FEATURED);
            }

            BusinessProfile::query()->whereKey($company->id)->update(['featured_position' => null]);
            PositionUpdate::apply('business_profiles', 'user_id', 'featured_position', array_values(array_diff($order, [$company->id])));
        });

        $this->audit->record($admin, 'admin.companies.unfeature', $company);
        $this->catalog->homeCurationChanged();
    }

    /**
     * @param array<array-key, mixed> $positions user id => typed position
     */
    public function reorder(User $admin, array $positions): void
    {
        $order = DB::transaction(function () use ($positions): array {
            $order = ManualOrder::resolve($this->lockedOrder(), $positions);
            PositionUpdate::apply('business_profiles', 'user_id', 'featured_position', $order);

            return $order;
        });

        $this->audit->record($admin, 'admin.companies.reorder', null, ['order' => $order]);
        $this->catalog->homeCurationChanged();
    }

    /**
     * The featured user ids in their current order, locked for the caller's
     * transaction.
     *
     * @return list<string>
     */
    private function lockedOrder(): array
    {
        /** @var list<string> */
        return BusinessProfile::query()
            ->whereNotNull('featured_position')
            ->orderBy('featured_position')
            ->orderBy('user_id')
            ->lockForUpdate()
            ->pluck('user_id')
            ->all();
    }
}
