'use client';

/**
 * Header bell — opens the NotificationsPopover.
 *
 * Self-contained: hidden when the user isn't authenticated. Uses the polling
 * `useUnreadNotificationsCountQuery` as the source of truth (WebSocket
 * events invalidate it on demand). The pill is clamped to "99+" so it never
 * wraps the small button.
 */
import { BellIcon } from '@/components/design-system/icons';
import { Button } from '@/components/ui/button';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { NotificationsPopover } from './NotificationsPopover';
import { useUnreadNotificationsCountQuery } from '@/lib/queries/notifications';
import { useAuth } from '@/hooks/useAuth';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

export function NotificationsBadge() {
  const { isAuthenticated, isHydrated } = useAuth();
  // Run the query so the Zustand store gets seeded + the badge stays live
  // even when the popover is closed.
  const { data } = useUnreadNotificationsCountQuery();

  if (!isHydrated || !isAuthenticated) return null;

  const total = data?.total ?? 0;
  const clamped = total > 99 ? '99+' : String(total);

  return (
    <Popover>
      <PopoverTrigger
        render={(triggerProps) => (
          <Button
            variant="ghost"
            size="icon"
            aria-label={t('notifications.header_label', 'الإشعارات')}
            className="relative"
            {...triggerProps}
          >
            <BellIcon className="size-5" aria-hidden />
            {total > 0 ? (
              <span
                className={cn(
                  'absolute -end-1 -top-1 inline-flex min-w-[18px] items-center justify-center rounded-full bg-coral px-1 text-[10px] font-bold leading-[18px] text-white',
                )}
                aria-label={t(
                  'notifications.header_label',
                  { count: String(total) },
                  `${total} غير مقروءة`,
                )}
              >
                {clamped}
              </span>
            ) : null}
          </Button>
        )}
      />
      <PopoverContent className="w-[22rem] max-w-[calc(100vw-2rem)]">
        <NotificationsPopover />
      </PopoverContent>
    </Popover>
  );
}
