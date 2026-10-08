'use client';

/**
 * Notifications index (455:14636, empty 461:14541): page title, the pill
 * tabs, then one row per notification. `?tab=` keeps the filter in the URL.
 */
import { useState } from 'react';
import { parseAsStringEnum, useQueryState } from 'nuqs';
import { Bell, CheckCheck, Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { Tab, TabList, TabPanel, Tabs } from '@/components/design-system/Tabs';
import { AccountPage, pillTabClass } from '@/components/account/AccountPage';
import { PanelState } from '@/components/account/PanelState';
import { Pager } from '@/components/account/Pager';
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

type NotificationTab = 'all' | 'unread';

const TABS: NotificationTab[] = ['all', 'unread'];

export function NotificationsClient() {
  const [tab, setTab] = useQueryState(
    'tab',
    parseAsStringEnum<NotificationTab>(TABS).withDefault('all'),
  );
  const [page, setPage] = useState(1);
  const { data: unread } = useUnreadNotificationsCountQuery();
  const markAllRead = useMarkAllNotificationsReadMutation();
  const unreadCount = unread?.total ?? 0;

  const handleTabChange = (next: NotificationTab) => {
    void setTab(next);
    setPage(1);
  };

  return (
    <AccountPage
      title={t('notifications.title', 'الإشعارات')}
      actions={
        <>
          {/* Hidden entirely while FCM env vars are absent. */}
          <EnablePushButton />
          {unreadCount > 0 ? (
            <Button variant="outline" size="sm" disabled={markAllRead.isPending} onClick={() => markAllRead.mutate()}>
              {markAllRead.isPending ? (
                <Loader2 className="animate-spin" aria-hidden="true" />
              ) : (
                <CheckCheck aria-hidden="true" />
              )}
              {t('notifications.mark_all_read', 'تعليم الكل كمقروء')}
            </Button>
          ) : null}
        </>
      }
    >
      <Tabs value={tab} onValueChange={(value) => handleTabChange(value as NotificationTab)}>
        <TabList aria-label={t('notifications.title', 'الإشعارات')} scrollOnPhones>
          {TABS.map((key) => (
            <Tab key={key} value={key} className={cn('gap-2', pillTabClass)}>
              {t(`notifications.tabs.${key}`)}
              {key === 'unread' && unreadCount > 0 ? (
                <span
                  className={cn(
                    'inline-flex min-w-[22px] items-center justify-center rounded-qb-pill px-1.5 text-qb-micro leading-[22px] font-semibold',
                    tab === 'unread' ? 'bg-qb-surface text-qb-ink' : 'bg-qb-brand text-white',
                  )}
                >
                  {unreadCount > 99 ? '99+' : unreadCount}
                </span>
              ) : null}
            </Tab>
          ))}
        </TabList>
        {TABS.map((key) => (
          <TabPanel key={key} value={key} className="mt-6 qb-tablet:mt-10">
            <NotificationsList tab={key} page={page} onPageChange={setPage} />
          </TabPanel>
        ))}
      </Tabs>
    </AccountPage>
  );
}

function NotificationsList({
  tab,
  page,
  onPageChange,
}: {
  tab: NotificationTab;
  page: number;
  onPageChange: (page: number) => void;
}) {
  const params =
    tab === 'unread'
      ? { page, per_page: PER_PAGE, unread: 1 as const }
      : { page, per_page: PER_PAGE };
  const { data, isLoading, isError, error } = useNotificationsQuery(params);
  const items = data?.data ?? [];

  if (isLoading) return <PanelState loading />;
  if (isError) {
    return (
      <PanelState
        loading={false}
        message={
          error instanceof ApiClientError
            ? translateMaybeKey(`notifications.errors.${error.code.toLowerCase()}`) || error.message
            : t('common.error', 'حدث خطأ، حاول مرة أخرى')
        }
      />
    );
  }
  if (items.length === 0) {
    return (
      <EmptyState
        icon={<Icon icon={Bell} size="lg" />}
        title={
          tab === 'unread'
            ? t('notifications.empty.unread', 'لا توجد إشعارات غير مقروءة')
            : t('notifications.empty.all', 'لا توجد إشعارات بعد')
        }
        description={t('notifications.empty_body')}
        className="rounded-qb-2xl border border-qb-line bg-qb-surface py-20 shadow-qb-card"
      />
    );
  }

  return (
    <>
      <ul className="flex flex-col gap-4">
        {items.map((n) => (
          <li key={n.id}>
            <NotificationRow notification={n} />
          </li>
        ))}
      </ul>
      <Pager page={page} lastPage={data?.meta.last_page ?? 1} onChange={onPageChange} />
    </>
  );
}
