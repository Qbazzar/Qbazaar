<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\AdStatus;
use App\Models\Ad;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Line chart of ads transitioning to ACTIVE over the last 30 days.
 *
 * Implementation notes:
 *  - Counts use `published_at` (not `created_at`) so the chart tracks public
 *    visibility rather than draft creation — much more useful for spotting
 *    moderation slowdowns vs. submission lulls.
 *  - A single GROUP BY DATE() query backs the whole 30-day window; we then
 *    walk the date range in PHP and zero-fill missing days so the line has
 *    no gaps. That keeps the chart's x-axis stable even on quiet days.
 */
class AdsPublishedChart extends ChartWidget
{
    /** How many days to render on the x-axis. */
    private const int WINDOW_DAYS = 30;

    protected ?string $heading = 'Ads published — last 30 days';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 5;

    /**
     * @return array{
     *     datasets: list<array{label: string, data: list<int>, borderColor?: string, backgroundColor?: string, tension?: float}>,
     *     labels: list<string>
     * }
     */
    protected function getData(): array
    {
        $start = CarbonImmutable::now()->subDays(self::WINDOW_DAYS - 1)->startOfDay();

        /** @var array<string, int> $rows */
        $rows = Ad::query()
            ->where('status', AdStatus::ACTIVE->value)
            ->where('published_at', '>=', $start)
            ->groupBy('day')
            ->orderBy('day')
            ->select([
                DB::raw('DATE(published_at) as day'),
                DB::raw('COUNT(*) as total'),
            ])
            ->pluck('total', 'day')
            ->map(static fn (mixed $v): int => (int) $v)
            ->all();

        $labels = [];
        $data = [];
        for ($i = 0; $i < self::WINDOW_DAYS; $i++) {
            $day = $start->addDays($i);
            $key = $day->toDateString();
            $labels[] = $day->format('M j');
            $data[] = $rows[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => __('admin.widgets.chart.ads_published'),
                    'data' => $data,
                    'borderColor' => '#f37335',
                    'backgroundColor' => 'rgba(243, 115, 53, 0.15)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    public function getHeading(): string
    {
        return (string) __('admin.widgets.chart.heading');
    }
}
