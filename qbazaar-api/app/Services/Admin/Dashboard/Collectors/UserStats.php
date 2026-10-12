<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard\Collectors;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Admin\Dashboard\DashboardWindow;
use App\Services\Admin\Dashboard\Trend;

/** One pass over `users` for every user tile. */
final class UserStats
{
    private const int ACTIVE_DAYS = 7;

    /**
     * @return list<array<string, mixed>>
     */
    public function tiles(DashboardWindow $window): array
    {
        $row = User::query()
            ->selectRaw(
                'COUNT(*) as total, '
                . 'COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as new_now, '
                . 'COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) as new_before, '
                . 'COALESCE(SUM(CASE WHEN account_type = ? THEN 1 ELSE 0 END), 0) as business, '
                . 'COALESCE(SUM(CASE WHEN account_type = ? THEN 1 ELSE 0 END), 0) as private_accounts, '
                . 'COALESCE(SUM(CASE WHEN phone_verified = 1 THEN 1 ELSE 0 END), 0) as verified, '
                . 'COALESCE(SUM(CASE WHEN last_login_at >= ? THEN 1 ELSE 0 END), 0) as active_recently',
                [
                    $window->start, $window->previousStart, $window->previousEnd,
                    AccountType::BUSINESS->value, AccountType::PRIVATE_INDIVIDUAL->value,
                    $window->end->subDays(self::ACTIVE_DAYS),
                ],
            )
            ->toBase()
            ->get()->sole();

        $total = (int) $row->total;

        return [
            ['key' => 'users_total', 'kind' => 'count', 'value' => $total],
            ['key' => 'users_new', 'kind' => 'count'] + Trend::between((int) $row->new_now, (int) $row->new_before),
            ['key' => 'users_business', 'kind' => 'count', 'value' => (int) $row->business],
            ['key' => 'users_private', 'kind' => 'count', 'value' => (int) $row->private_accounts],
            ['key' => 'users_verified', 'kind' => 'percent', 'value' => $total > 0 ? (int) round((int) $row->verified / $total * 100) : 0],
            ['key' => 'users_active', 'kind' => 'count', 'value' => (int) $row->active_recently],
        ];
    }
}
