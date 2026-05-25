<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Revenue placeholder card.
 *
 * Sprint 12 will wire featured-ad billing through, and these stats will draw
 * from `transactions` and `subscriptions`. We ship the card now so the
 * dashboard layout stabilises and operators don't reorder widgets later.
 *
 * Returns hard-coded zeros — never a fake placeholder number — because
 * stakeholders shouldn't see a value they could mistake for live data.
 */
class RevenueStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make(__('admin.widgets.revenue.mtd'), '— QAR')
                ->description(__('admin.widgets.revenue.coming_sprint_12'))
                ->color('gray'),

            Stat::make(__('admin.widgets.revenue.featured_active'), '0')
                ->description(__('admin.widgets.revenue.coming_sprint_12'))
                ->color('gray'),

            Stat::make(__('admin.widgets.revenue.subscriptions'), '0')
                ->description(__('admin.widgets.revenue.coming_sprint_12'))
                ->color('gray'),
        ];
    }
}
