<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\Dashboard\DashboardPeriod;
use App\Services\Admin\Dashboard\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The admin home. Which sections it shows is decided by the staff member's
 * permissions inside {@see DashboardStatsService}; this only picks the period.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DashboardStatsService $stats): View
    {
        /** @var User $user */
        $user = $request->user();
        $period = DashboardPeriod::fromRequest($request->query('period'));

        return view('admin.dashboard', [
            'stats' => $stats->forUser($user, $period),
            'period' => $period,
        ]);
    }
}
