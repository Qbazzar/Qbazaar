<?php

declare(strict_types=1);

namespace App\Services\Admin\Broadcasts;

use App\Enums\AccountType;
use App\Enums\BroadcastSegment;
use App\Enums\UserStatus;
use App\Models\Ad;
use App\Models\StaffBroadcast;
use App\Models\User;
use App\Services\Catalog\LocationHierarchy;
use Illuminate\Database\Eloquent\Builder;

/**
 * The users a broadcast reaches, always active accounts only.
 *
 *  - all: the users (status, deleted_at) index.
 *  - businesses: users_directory_idx (account_type, status, …).
 *  - city: users with a publicly listed ad in the city or any district or
 *    area under it (ads_location_status_published_idx), since users store
 *    no home city of their own.
 */
class BroadcastAudience
{
    public function __construct(private readonly LocationHierarchy $locations) {}

    /**
     * @return Builder<User>
     */
    public function query(StaffBroadcast $broadcast): Builder
    {
        $users = User::query()->where('status', UserStatus::ACTIVE->value);

        return match ($broadcast->segment) {
            BroadcastSegment::ALL => $users,
            BroadcastSegment::BUSINESSES => $users->where('account_type', AccountType::BUSINESS->value),
            BroadcastSegment::CITY => $users->whereIn('id', $this->sellersIn((string) $broadcast->location_id)),
        };
    }

    /**
     * @return Builder<Ad>
     */
    private function sellersIn(string $locationId): Builder
    {
        return Ad::query()
            ->publiclyListed()
            ->whereIn('location_id', $this->locations->descendantsOf($locationId))
            ->select('user_id');
    }
}
