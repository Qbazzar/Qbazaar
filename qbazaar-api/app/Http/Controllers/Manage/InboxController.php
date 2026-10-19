<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\Inbox\StaffInbox;
use App\Services\Admin\Inbox\WorkQueues;
use App\Services\Admin\Moderation\SlaClock;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in staff member's own bell. Every staff member has one, like
 * their profile; the work queues inside check their own permissions.
 */
class InboxController extends Controller
{
    public function __construct(private readonly StaffInbox $inbox) {}

    public function index(Request $request, #[CurrentUser] User $staff, WorkQueues $queues): View
    {
        $unreadOnly = $request->string('filter')->toString() === StaffInbox::FILTER_UNREAD;

        return view('admin.inbox.index', [
            'entries' => $this->inbox->page($staff, $unreadOnly, (int) config('qbazaar.admin.inbox_per_page')),
            'unreadOnly' => $unreadOnly,
            'unreadCount' => $this->inbox->unreadCount($staff),
            'queues' => $queues->for($staff, SlaClock::fromConfig()),
        ]);
    }

    /** What the bell polls: the unread count only, cached per person. */
    public function summary(#[CurrentUser] User $staff): JsonResponse
    {
        return response()->json(['unread' => $this->inbox->unreadCount($staff)]);
    }

    public function open(#[CurrentUser] User $staff, string $notification): RedirectResponse
    {
        $target = $this->inbox->open($staff, $notification);

        return $target !== null ? redirect()->to($target) : redirect()->route('admin.inbox.index');
    }

    public function readAll(#[CurrentUser] User $staff): RedirectResponse
    {
        $this->inbox->markAllRead($staff);

        return back()->with('status', __('admin.inbox.read_all_done'));
    }
}
