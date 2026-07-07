<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $read = $request->string('read')->toString();

        $notifications = DatabaseNotification::query()
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($inner) use ($search): void {
                    $inner->where('data->title', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                }),
            )
            ->when($read === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when($read === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

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
            'search' => $search,
            'read' => $read,
        ]);
    }
}
