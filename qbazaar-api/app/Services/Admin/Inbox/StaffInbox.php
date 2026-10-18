<?php

declare(strict_types=1);

namespace App\Services\Admin\Inbox;

use App\Models\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A staff member's own bell: the notifications the panel already writes for
 * them (pending ads, new reports, support tickets, finance reviews), read
 * newest first through notifications_notifiable_created_idx.
 *
 * The unread count sits on every admin page and is polled by the bell, so it
 * is cached per person for a few seconds and forgotten whenever they read.
 */
class StaffInbox
{
    public const string FILTER_UNREAD = 'unread';

    private const string UNREAD_KEY = 'admin:inbox:unread:';

    public function __construct(
        private readonly Cache $cache,
    ) {}

    public function unreadCount(User $staff): int
    {
        return (int) $this->cache->remember(
            self::UNREAD_KEY . $staff->getKey(),
            max(1, (int) config('qbazaar.admin.inbox_unread_cache_seconds')),
            static fn (): int => $staff->unreadNotifications()->count(),
        );
    }

    /**
     * @return Paginator<int, InboxEntry>
     */
    public function page(User $staff, bool $unreadOnly, int $perPage): Paginator
    {
        return $staff->notifications()
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->simplePaginate($perPage, ['id', 'type', 'category', 'data', 'read_at', 'created_at'])
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification): InboxEntry => InboxEntry::from($notification));
    }

    /**
     * Marks one of the staff member's own notifications read and returns the
     * in-panel page it points to, if it points to one. Someone else's id is a
     * 404, never a hint that it exists.
     */
    public function open(User $staff, string $notificationId): ?string
    {
        /** @var DatabaseNotification $notification */
        $notification = $staff->notifications()->whereKey($notificationId)->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
            $this->forget($staff);
        }

        $data = (array) $notification->data;

        return AdminTarget::from(is_string($data['cta_url'] ?? null) ? $data['cta_url'] : null);
    }

    public function markAllRead(User $staff): int
    {
        $marked = $staff->unreadNotifications()->update(['read_at' => now()]);
        $this->forget($staff);

        return $marked;
    }

    private function forget(User $staff): void
    {
        $this->cache->forget(self::UNREAD_KEY . $staff->getKey());
    }
}
