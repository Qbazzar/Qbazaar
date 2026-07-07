<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\AdStatus;
use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const TREND_DAYS = 14;

    public function index(): View
    {
        return view('admin.dashboard', [
            'ads' => $this->adStats(),
            'users' => $this->userStats(),
            'reports' => $this->reportStats(),
            'trend' => $this->publishedTrend(),
            'pendingAds' => Ad::with(['user', 'category'])
                ->where('status', AdStatus::PENDING->value)
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }

    /** @return array{active:int,pending:int,published_today:int,total:int} */
    private function adStats(): array
    {
        return [
            'total' => Ad::count(),
            'active' => Ad::where('status', AdStatus::ACTIVE->value)->count(),
            'pending' => Ad::where('status', AdStatus::PENDING->value)->count(),
            'published_today' => Ad::where('status', AdStatus::ACTIVE->value)
                ->where('published_at', '>=', now()->startOfDay())
                ->count(),
        ];
    }

    /** @return array{total:int,active:int,active_today:int,new_week:int} */
    private function userStats(): array
    {
        return [
            'total' => User::count(),
            'active' => User::where('status', UserStatus::ACTIVE->value)->count(),
            'active_today' => User::where('last_login_at', '>=', now()->startOfDay())->count(),
            'new_week' => User::where('created_at', '>=', now()->startOfWeek())->count(),
        ];
    }

    /** @return array{pending:int,actioned_week:int,dismissed_week:int} */
    private function reportStats(): array
    {
        return [
            'pending' => Report::where('status', ReportStatus::PENDING->value)->count(),
            'actioned_week' => Report::where('status', ReportStatus::ACTIONED->value)
                ->where('reviewed_at', '>=', now()->startOfWeek())->count(),
            'dismissed_week' => Report::where('status', ReportStatus::DISMISSED->value)
                ->where('reviewed_at', '>=', now()->startOfWeek())->count(),
        ];
    }

    /**
     * Ads published per day over the trailing window, zero-filled so every day
     * renders even with no activity.
     *
     * @return list<array{label:string,count:int}>
     */
    private function publishedTrend(): array
    {
        $start = CarbonImmutable::now()->subDays(self::TREND_DAYS - 1)->startOfDay();

        $counts = Ad::where('status', AdStatus::ACTIVE->value)
            ->where('published_at', '>=', $start)
            ->groupBy('day')
            ->select([DB::raw('DATE(published_at) as day'), DB::raw('COUNT(*) as total')])
            ->pluck('total', 'day');

        $trend = [];
        for ($i = 0; $i < self::TREND_DAYS; $i++) {
            $day = $start->addDays($i);
            $trend[] = [
                'label' => $day->format('m/d'),
                'count' => (int) ($counts[$day->format('Y-m-d')] ?? 0),
            ];
        }

        return $trend;
    }
}
