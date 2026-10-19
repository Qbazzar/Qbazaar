<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\BroadcastSegment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\Broadcasts\StoreBroadcastRequest;
use App\Models\User;
use App\Services\Admin\Broadcasts\BroadcastComposer;
use App\Services\Admin\Broadcasts\BroadcastHistory;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The broadcast composer and the history of sent broadcasts (AD-17.15).
 * Delivery is queued; the history shows its progress.
 */
class BroadcastController extends Controller
{
    public function index(#[CurrentUser] User $staff, BroadcastHistory $history, BroadcastComposer $composer): View
    {
        return view('admin.broadcasts.index', [
            'broadcasts' => $history->latest(),
            'cities' => $history->cities(),
            'segments' => BroadcastSegment::cases(),
            'remaining' => $composer->remaining($staff),
            'locale' => app()->getLocale(),
        ]);
    }

    public function store(StoreBroadcastRequest $request, #[CurrentUser] User $staff, BroadcastComposer $composer): RedirectResponse
    {
        $composer->compose($staff, $request->draft());

        return redirect()->route('admin.broadcasts.index')->with('status', __('admin.broadcasts.flash.queued'));
    }
}
