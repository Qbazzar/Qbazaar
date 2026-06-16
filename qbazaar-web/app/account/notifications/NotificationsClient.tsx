'use client';

/**
 * Notifications index — QBFront port (source: QBFront/notifications.html).
 *
 * Layout: `.notif-page` container · `.notif-head` (title + mark-all CTA) ·
 * `.notif-filters` (chip tabs) · `.notif-list` (rows).
 */
import { useState } from 'react';
import { parseAsStringEnum, useQueryState } from 'nuqs';
import {
  BellIcon,
  CheckCheckIcon,
  Loader2Icon,
} from 'lucide-react';

import { Button } from '@/components/ui/button';
import { EnablePushButton } from '@/components/notifications/EnablePushButton';
import { NotificationRow } from '@/components/notifications/NotificationRow';
import {
  useMarkAllNotificationsReadMutation,
  useNotificationsQuery,
  useUnreadNotificationsCountQuery,
} from '@/lib/queries/notifications';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { ApiClientError } from '@/lib/api/auth';
import { cn } from '@/lib/utils';

const PER_PAGE = 20;

type Tab = 'all' | 'unread';

export function NotificationsClient() {
  const [tab, setTab] = useQueryState(
    'tab',
    parseAsStringEnum<Tab>(['all', 'unread']).withDefault('all'),
  );
  const [page, setPage] = useState(1);

  const params =
    tab === 'unread'
      ? { page, per_page: PER_PAGE, unread: 1 as const }
      : { page, per_page: PER_PAGE };

  const { data, isLoading, isError, error } = useNotificationsQuery(params);
  const { data: unread } = useUnreadNotificationsCountQuery();
  const markAllRead = useMarkAllNotificationsReadMutation();

  const lastPage = data?.meta.last_page ?? 1;
  const unreadCount = unread?.total ?? 0;
  const items = data?.data ?? [];

  const handleTabChange = (next: Tab) => {
    void setTab(next);
    setPage(1);
  };

  return (
    <div className="container notif-page" style={{ paddingTop: 24, paddingBottom: 48 }}>
      <div className="notif-head">
        <div>
          <h1 className="notif-head__h">
            {t('notifications.title', 'الإشعارات')}
          </h1>
          <p className="notif-head__sub">
            {t(
              'notifications.subtitle',
              'كل الإشعارات الجديدة والقديمة في مكان واحد.',
            )}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {/* Hidden entirely while FCM env vars are absent. */}
          <EnablePushButton />
          {unreadCount > 0 ? (
            <Button
              type="button"
              variant="outline"
              size="default"
              className="rounded-full"
              disabled={markAllRead.isPending}
              onClick={() => markAllRead.mutate()}
            >
              {markAllRead.isPending ? (
                <Loader2Icon className="size-3.5 animate-spin" aria-hidden />
              ) : (
                <CheckCheckIcon className="size-3.5" aria-hidden />
              )}
              {t('notifications.mark_all_read', 'تعليم الكل كمقروء')}
            </Button>
          ) : null}
        </div>
      </div>

      <div className="notif-filters" role="tablist" aria-label={t('notifications.title', 'الإشعارات')}>
        <TabChip
          active={tab === 'all'}
          onClick={() => handleTabChange('all')}
          label={t('notifications.tabs.all', 'الكل')}
        />
        <TabChip
          active={tab === 'unread'}
          onClick={() => handleTabChange('unread')}
          label={t('notifications.tabs.unread', 'غير المقروء')}
          badge={unreadCount > 0 ? unreadCount : undefined}
        />
      </div>

      {isLoading ? (
        <div className="flex justify-center py-12" role="status">
          <Loader2Icon
            className="text-muted-foreground size-6 animate-spin"
            aria-hidden
          />
        </div>
      ) : isError ? (
        <p className="text-destructive py-12 text-center text-sm">
          {error instanceof ApiClientError
            ? translateMaybeKey(
                `notifications.errors.${error.code.toLowerCase()}`,
              ) || error.message
            : t('common.error', 'حدث خطأ، حاول مرة أخرى')}
        </p>
      ) : items.length === 0 ? (
        <EmptyState tab={tab} />
      ) : (
        <>
          <ul className="notif-list" style={{ display: 'block' }}>
            {items.map((n) => (
              <li key={n.id} style={{ listStyle: 'none' }}>
                <NotificationRow notification={n} />
              </li>
            ))}
          </ul>

          {lastPage > 1 ? (
            <div className="pagination">
              <button
                type="button"
                className="pagination__num"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
                aria-label={t('ads.list.prev', 'السابق')}
              >
                ‹
              </button>
              <span className="pagination__gap">
                {t(
                  'ads.list.page_of',
                  { current: String(page), total: String(lastPage) },
                  `${page} / ${lastPage}`,
                )}
              </span>
              <button
                type="button"
                className="pagination__num"
                onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                disabled={page >= lastPage}
                aria-label={t('ads.list.next', 'التالي')}
              >
                ›
              </button>
            </div>
          ) : null}
        </>
      )}
    </div>
  );
}

function TabChip({
  active,
  onClick,
  label,
  badge,
}: {
  active: boolean;
  onClick: () => void;
  label: string;
  badge?: number;
}) {
  return (
    <button
      type="button"
      role="tab"
      aria-selected={active}
      onClick={onClick}
      className={cn('chip', active && 'is-active')}
    >
      <span>{label}</span>
      {typeof badge === 'number' ? (
        <span
          className={cn(
            'inline-flex min-w-[18px] items-center justify-center rounded-full px-1 text-[10px] font-bold leading-[18px]',
            active ? 'bg-white text-coral' : 'bg-coral text-white',
          )}
        >
          {badge > 99 ? '99+' : badge}
        </span>
      ) : null}
    </button>
  );
}

function EmptyState({ tab }: { tab: Tab }) {
  return (
    <div className="empty-state">
      <div className="bg-coral/10 text-coral mx-auto grid size-12 place-items-center rounded-full">
        <BellIcon className="size-5" aria-hidden />
      </div>
      <div className="empty-state__title">
        {tab === 'unread'
          ? t('notifications.empty.unread', 'لا توجد إشعارات غير مقروءة')
          : t('notifications.empty.all', 'لا توجد إشعارات بعد')}
      </div>
    </div>
  );
}
