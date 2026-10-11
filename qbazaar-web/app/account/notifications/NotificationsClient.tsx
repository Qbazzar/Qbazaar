'use client';

/**
 * Notifications index of notifications.html (455:14636, empty 461:14541):
 * the four filter pills, "Select All" with "Read All" / "Delete" for the
 * selected rows, then one row per notification. `?tab=` keeps the filter
 * in the URL.
 */
import { useState, type ReactNode } from 'react';
import { parseAsStringEnum, useQueryState } from 'nuqs';
import { toast } from 'sonner';
import { Bell, Check, Loader2, Trash2 } from 'lucide-react';

import { showDesignToast } from '@/components/design-system/design-toast';
import { focusRing } from '@/components/design-system/focus-ring';
import { TabList, TabPanel, Tabs } from '@/components/design-system/Tabs';
import { AccountEmptyState } from '@/components/account/AccountEmptyState';
import { AccountPage } from '@/components/account/AccountPage';
import { PanelState } from '@/components/account/PanelState';
import { Pager } from '@/components/account/Pager';
import { PillTab } from '@/components/account/PillTab';
import { SelectCheckbox } from '@/components/account/SelectCheckbox';
import { EnablePushButton } from '@/components/notifications/EnablePushButton';
import { NotificationRow } from '@/components/notifications/NotificationRow';
import type { ListNotificationsParams } from '@/lib/api/notifications';
import { useBulkNotificationsMutation, useNotificationsQuery } from '@/lib/queries/notifications';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { ApiClientError } from '@/lib/api/auth';
import { cn } from '@/lib/utils';

const PER_PAGE = 20;

type NotificationTab = 'all' | 'price' | 'unread' | 'save';

const TABS: NotificationTab[] = ['all', 'price', 'unread', 'save'];

/** The API filter behind each pill: "Save Search" takes the whole `search.*` group. */
const TAB_FILTERS: Record<NotificationTab, Omit<ListNotificationsParams, 'page' | 'per_page'>> = {
  all: {},
  price: { category: 'ad.price_changed' },
  unread: { unread: 1 },
  save: { category: 'search' },
};

export function NotificationsClient() {
  const [tab, setTab] = useQueryState('tab', parseAsStringEnum<NotificationTab>(TABS).withDefault('all'));
  const [page, setPage] = useState(1);

  const handleTabChange = (next: NotificationTab) => {
    void setTab(next);
    setPage(1);
  };

  return (
    <AccountPage title={t('notifications.page_title')} actions={<EnablePushButton />}>
      <Tabs value={tab} onValueChange={(value) => handleTabChange(value as NotificationTab)}>
        <TabList aria-label={t('notifications.filters_label')} scrollOnPhones className="gap-4">
          {TABS.map((key) => (
            <PillTab key={key} value={key}>
              {t(`notifications.tabs.${key}`)}
            </PillTab>
          ))}
        </TabList>
        {TABS.map((key) => (
          <TabPanel key={key} value={key} className="mt-[26px]">
            {/* Remounts per tab, so the selection never spans two filters. */}
            {key === tab ? <NotificationsList tab={key} page={page} onPageChange={setPage} /> : null}
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
  const { data, isLoading, isError, error } = useNotificationsQuery({ page, per_page: PER_PAGE, ...TAB_FILTERS[tab] });
  const [selected, setSelected] = useState<ReadonlySet<string>>(() => new Set());
  const bulk = useBulkNotificationsMutation();
  const items = data?.data ?? [];
  const visibleSelected = items.filter((n) => selected.has(n.id));
  const allSelected = items.length > 0 && visibleSelected.length === items.length;

  const toggle = (id: string, checked: boolean) =>
    setSelected((current) => {
      const next = new Set(current);
      if (checked) next.add(id);
      else next.delete(id);
      return next;
    });

  const runBulk = (action: 'read' | 'delete') => {
    const ids = visibleSelected.map((n) => n.id);
    bulk.mutate(
      { action, ids },
      {
        onSuccess: (failed) => {
          setSelected(new Set());
          if (failed > 0) toast.error(t('notifications.bulk_failed'));
          else showDesignToast(t(action === 'read' ? 'notifications.bulk_read' : 'notifications.bulk_deleted', { count: ids.length }));
        },
      },
    );
  };

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

  return (
    <>
      <div className="mb-[22px] flex items-center justify-between gap-3">
        <label className="flex cursor-pointer items-center gap-3 text-qb-body-lg text-qb-ink-body">
          <SelectCheckbox
            checked={allSelected}
            disabled={items.length === 0}
            onChange={(event) => setSelected(event.target.checked ? new Set(items.map((n) => n.id)) : new Set())}
          />
          {t('notifications.select_all')}
        </label>
        {visibleSelected.length > 0 ? (
          // Overlaps the row's margins, so the list does not move when the buttons appear.
          <div className="-my-2.5 flex items-center gap-3">
            <BulkButton
              icon={<Check aria-hidden="true" />}
              label={t('notifications.read_all')}
              pending={bulk.isPending && bulk.variables?.action === 'read'}
              disabled={bulk.isPending}
              onClick={() => runBulk('read')}
              className="border border-qb-line bg-qb-surface text-qb-ink-body hover:bg-qb-hover"
            />
            <BulkButton
              icon={<Trash2 aria-hidden="true" />}
              label={t('notifications.delete_selected')}
              pending={bulk.isPending && bulk.variables?.action === 'delete'}
              disabled={bulk.isPending}
              onClick={() => runBulk('delete')}
              className="bg-qb-danger-soft text-qb-danger"
            />
          </div>
        ) : null}
      </div>

      {items.length === 0 ? (
        <AccountEmptyState
          icon={<Bell strokeWidth={1.6} />}
          title={t('notifications.empty.title')}
          description={t('notifications.empty.filter_body')}
        />
      ) : (
        <ul className="flex flex-col gap-4">
          {items.map((n) => (
            <li key={n.id} className="flex items-center gap-4">
              <SelectCheckbox
                checked={selected.has(n.id)}
                onChange={(event) => toggle(n.id, event.target.checked)}
                aria-label={t('notifications.select_one', { title: n.title })}
              />
              <NotificationRow notification={n} className="min-w-0 flex-1" />
            </li>
          ))}
        </ul>
      )}
      <Pager page={page} lastPage={data?.meta.last_page ?? 1} onChange={onPageChange} />
    </>
  );
}

/** "Read All" / "Delete" of the selection: labelled buttons from the tablet layout up, icon squares on phones. */
function BulkButton({
  icon,
  label,
  pending,
  disabled,
  onClick,
  className,
}: {
  icon: ReactNode;
  label: string;
  pending: boolean;
  disabled: boolean;
  onClick: () => void;
  className: string;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      className={cn(
        'inline-flex size-11 cursor-pointer items-center justify-center gap-2 rounded-qb-md text-qb-body font-medium disabled:cursor-progress disabled:opacity-60 qb-tablet:h-11 qb-tablet:w-auto qb-tablet:px-5 [&_svg]:size-[18px]',
        focusRing,
        className,
      )}
    >
      {pending ? <Loader2 className="animate-spin" aria-hidden="true" /> : icon}
      <span aria-hidden="true" className="hidden qb-tablet:inline">
        {label}
      </span>
    </button>
  );
}
