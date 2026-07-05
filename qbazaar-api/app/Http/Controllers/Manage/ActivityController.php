<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    public function index(): View
    {
        $activities = Activity::query()
            ->with(['causer', 'subject'])
            ->latest()
            ->paginate(30);

        return view('manage.activity.index', ['activities' => $activities]);
    }
}
