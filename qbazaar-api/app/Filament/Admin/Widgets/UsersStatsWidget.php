<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\UserStatus;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard "at-a-glance" card for the user base.
 *
 *  - Total users excludes soft-deleted rows because soft-deletes here are a
 *    GDPR-driven "pending deletion" flag, not a meaningful headcount metric.
 *  - "Active today" uses `last_login_at` rather than ad-publishing activity
 *    so the metric stays stable when the platform is in low-volume hours.
 */
class UsersStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $total = User::query()->count();

        $activeToday = User::query()
            ->where('last_login_at', '>=', now()->startOfDay())
            ->count();

        $newThisWeek = User::query()
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        return [
            Stat::make(__('admin.widgets.users.total'), (string) $total)
                ->description(__('admin.widgets.users.active_label', ['count' => $this->activeUserCount()]))
                ->color('primary'),

            Stat::make(__('admin.widgets.users.active_today'), (string) $activeToday)
                ->description(__('admin.widgets.users.last_24h'))
                ->color('success'),

            Stat::make(__('admin.widgets.users.new_this_week'), (string) $newThisWeek)
                ->description(__('admin.widgets.users.since_monday'))
                ->color('warning'),
        ];
    }

    private function activeUserCount(): int
    {
        return User::query()
            ->where('status', UserStatus::ACTIVE->value)
            ->count();
    }
}
