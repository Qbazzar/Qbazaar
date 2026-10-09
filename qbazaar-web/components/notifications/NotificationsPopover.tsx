'use client';

/**
 * Popover body for the header bell. Renders the latest 10 notifications and
 * the "Mark all as read" + "View all" footer actions.
 *
 * Clicking a row marks it read optimistically and — if the notification has
 * a `cta_url` — navigates to it via Next router.push (works for both internal
 * and external URLs; external ones still open in the same tab to keep the UX
 * uniform with email links).
 */
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { CheckCheckIcon, Loader2Icon } from 'lucide-react';
import { DynamicIcon } from '@/components/ui/dynamic-icon';
import { Button } from '@/components/ui/button';
import {
  useMarkAllNotificationsReadMutation,
  useMarkNotificationReadMutation,
  useNotificationsQuery,
} from '@/lib/queries/notifications';
import { formatRelativeTime } from '@/components/messaging/relative-time';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { Notification } from '@/lib/api/types';

const PREVIEW_PER_PAGE = 10;

export function NotificationsPopover() {
  const router = useRouter();
  const { data, isLoading } = useNotificationsQuery({
    page: 1,
    per_page: PREVIEW_PER_PAGE,
  });
  const markRead = useMarkNotificationReadMutation();
  const markAllRead = useMarkAllNotificationsReadMutation();

  const items = data?.data ?? [];
  const hasUnread = items.some((n) => !n.read_at);

  const handleRowClick = (n: Notification) => {
    if (!n.read_at) markRead.mutate(n.id);
    if (n.cta_url) router.push(n.cta_url);
  };

  return (
    <div className="flex max-h-[28rem] flex-col">
      <header className="border-ink-200 flex items-center justify-between gap-2 border-b px-3 py-2.5">
        <h2 className="font-qb text-ink-900 text-sm font-semibold">
          {t('notifications.title', 'الإشعارات')}
        </h2>
      </header>

      <div className="flex-1 overflow-y-auto">
        {isLoading ? (
          <div className="flex items-center justify-center py-10" role="status">
            <Loader2Icon
              className="text-muted-foreground size-5 animate-spin"
              aria-hidden
            />
          </div>
        ) : items.length === 0 ? (
          <p className="text-ink-500 px-4 py-10 text-center text-sm">
            {t('notifications.empty.all', 'لا توجد إشعارات بعد')}
          </p>
        ) : (
          <ul className="divide-ink-200/60 divide-y">
            {items.map((n) => (
              <li key={n.id}>
                <button
                  type="button"
                  onClick={() => handleRowClick(n)}
                  className={cn(
                    'hover:bg-cream-200/60 focus-visible:bg-cream-200/60 flex w-full items-start gap-3 px-3 py-3 text-start transition-colors outline-none',
                    !n.read_at && 'bg-coral/5',
                  )}
                >
                  <span
                    className={cn(
                      'bg-cream-200 text-ink-700 grid size-9 shrink-0 place-items-center rounded-full',
                      !n.read_at && 'bg-coral/15 text-coral',
                    )}
                    aria-hidden
                  >
                    <DynamicIcon name={n.icon} className="size-4" />
                  </span>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <p className="text-ink-900 truncate text-sm font-medium">
                        {n.title}
                      </p>
                      {!n.read_at ? (
                        <span
                          aria-hidden
                          className="bg-coral size-1.5 shrink-0 rounded-full"
                        />
                      ) : null}
                    </div>
                    <p className="text-ink-500 mt-0.5 line-clamp-2 text-xs">
                      {n.body}
                    </p>
                    <p className="text-ink-400 mt-1 text-[10px]">
                      {formatRelativeTime(n.created_at)}
                    </p>
                  </div>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      <footer className="border-ink-200 flex items-center justify-between gap-2 border-t px-3 py-2">
        <Button
          type="button"
          variant="ghost"
          size="xs"
          disabled={!hasUnread || markAllRead.isPending}
          onClick={() => markAllRead.mutate()}
          className="text-coral hover:bg-coral/10 disabled:opacity-40"
        >
          <CheckCheckIcon className="size-3.5" aria-hidden />
          {t('notifications.mark_all_read', 'تعليم الكل كمقروء')}
        </Button>
        <Link
          href="/account/notifications"
          className="text-coral hover:bg-coral/10 rounded-md px-2 py-1 text-xs font-medium"
        >
          {t('notifications.view_all', 'عرض جميع الإشعارات')}
        </Link>
      </footer>
    </div>
  );
}
