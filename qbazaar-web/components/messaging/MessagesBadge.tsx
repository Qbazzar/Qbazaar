'use client';

/**
 * Header badge — links to /account/messages with a count pill.
 *
 * Self-contained: hidden when the user isn't authenticated. Uses the polling
 * `useUnreadCountQuery` as the source of truth (WebSocket events invalidate
 * it on demand). The pill is clamped to "99+" so it never wraps.
 */
import Link from 'next/link';
import { MessageSquareIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useUnreadCountQuery } from '@/lib/queries/messaging';
import { useAuth } from '@/hooks/useAuth';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

export function MessagesBadge() {
  const { isAuthenticated, isHydrated } = useAuth();
  const { data } = useUnreadCountQuery();

  if (!isHydrated || !isAuthenticated) return null;

  const total = data?.total ?? 0;
  const clamped = total > 99 ? '99+' : String(total);

  return (
    <Button
      asChild
      variant="ghost"
      size="icon"
      aria-label={t('account.nav.messages', 'الرسائل')}
      className="relative"
    >
      <Link href="/account/messages">
        <MessageSquareIcon className="size-5" aria-hidden />
        {total > 0 ? (
          <span
            className={cn(
              'absolute -end-1 -top-1 inline-flex min-w-[18px] items-center justify-center rounded-full bg-coral px-1 text-[10px] font-bold leading-[18px] text-white',
            )}
            aria-label={t(
              'messaging.unread_count',
              { count: String(total) },
              `${total} غير مقروءة`,
            )}
          >
            {clamped}
          </span>
        ) : null}
      </Link>
    </Button>
  );
}
