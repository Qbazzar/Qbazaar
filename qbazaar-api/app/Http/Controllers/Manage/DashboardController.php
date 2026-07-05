<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\AdStatus;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Report;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'ads_total' => Ad::count(),
            'ads_active' => Ad::where('status', AdStatus::ACTIVE->value)->count(),
            'ads_pending' => Ad::where('status', AdStatus::PENDING->value)->count(),
            'users_total' => User::count(),
            'reports_pending' => Report::where('status', ReportStatus::PENDING->value)->count(),
        ];

        $pendingAds = Ad::with(['user', 'category'])
            ->where('status', AdStatus::PENDING->value)
            ->latest()
            ->limit(8)
            ->get();

        return view('manage.dashboard', compact('stats', 'pendingAds'));
    }
}
