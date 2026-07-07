<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $logName = $request->string('log_name')->toString();

        $logNames = Activity::query()
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name')
            ->filter()
            ->values();

        $activities = Activity::query()
            ->with(['causer', 'subject'])
            ->when(
                $search !== '',
                fn ($query) => $query->where('description', 'like', "%{$search}%"),
            )
            ->when(
                $logName !== '' && $logNames->contains($logName),
                fn ($query) => $query->where('log_name', $logName),
            )
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.activity.index', [
            'activities' => $activities,
            'search' => $search,
            'logName' => $logName,
            'logNames' => $logNames,
        ]);
    }
}
