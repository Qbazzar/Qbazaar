<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\AdStatus;
use App\Models\Ad;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ad inventory snapshot.
 *
 * The "pending moderation" stat surfaces the moderator queue size at a glance —
 * the same number the ReportsStats card shows for reports, so admins can pick
 * which queue to drain first.
 */
class AdsStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $active = Ad::query()
            ->where('status', AdStatus::ACTIVE->value)
            ->count();

        $pending = Ad::query()
            ->where('status', AdStatus::PENDING->value)
            ->count();

        $publishedToday = Ad::query()
            ->where('status', AdStatus::ACTIVE->value)
            ->where('published_at', '>=', now()->startOfDay())
            ->count();

        return [
            Stat::make(__('admin.widgets.ads.active_total'), (string) $active)
                ->description(__('admin.widgets.ads.live_now'))
                ->color('success'),

            Stat::make(__('admin.widgets.ads.pending_moderation'), (string) $pending)
                ->description(__('admin.widgets.ads.awaiting_review'))
                ->color($pending > 0 ? 'warning' : 'gray'),

            Stat::make(__('admin.widgets.ads.published_today'), (string) $publishedToday)
                ->description(__('admin.widgets.ads.since_midnight'))
                ->color('primary'),
        ];
    }
}
