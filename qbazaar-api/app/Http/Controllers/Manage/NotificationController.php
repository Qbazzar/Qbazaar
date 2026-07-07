<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = DatabaseNotification::query()
            ->latest()
            ->paginate(20);

        // Resolve User notifiables in a single query to avoid N+1 name lookups.
        $userIds = $notifications->getCollection()
            ->filter(static fn (DatabaseNotification $n): bool => is_a((string) $n->getAttribute('notifiable_type'), User::class, true))
            ->pluck('notifiable_id')
            ->unique();

        $userNames = User::query()
            ->whereIn('id', $userIds)
            ->pluck('full_name', 'id');

        return view('admin.notifications.index', [
            'notifications' => $notifications,
            'userNames' => $userNames,
        ]);
    }
}
