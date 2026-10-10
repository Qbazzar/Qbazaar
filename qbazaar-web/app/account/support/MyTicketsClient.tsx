'use client';

/**
 * My support tickets — no frame of its own, so it follows the closest
 * reference (DESIGN-MAP): the My Ads page of 518:20536, with the pill tabs
 * filtering by status and one row per ticket. `?tab=` keeps the filter in
 * the URL. Auth-gated by the parent /account/layout.
 */
import { useState } from 'react';
import Link from 'next/link';
import { parseAsStringEnum, useQueryState } from 'nuqs';
import { LifeBuoy, Plus } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { TabList, TabPanel, Tabs } from '@/components/design-system/Tabs';
import { AccountPage } from '@/components/account/AccountPage';
import { PillTab } from '@/components/account/PillTab';
import { PanelState } from '@/components/account/PanelState';
import { Pager } from '@/components/account/Pager';
import { TicketRow } from '@/components/support/TicketRow';
import { useMyTicketsQuery } from '@/lib/queries/support';
import { t } from '@/lib/i18n/messages';
import type { SupportTicketStatus } from '@/lib/api/types';

const PER_PAGE = 20;

const TABS = ['all', 'open', 'in_progress', 'resolved'] as const satisfies readonly ('all' | SupportTicketStatus)[];

type TicketTab = (typeof TABS)[number];

export function MyTicketsClient() {
  const [tab, setTab] = useQueryState('tab', parseAsStringEnum<TicketTab>([...TABS]).withDefault('all'));
  const [page, setPage] = useState(1);

  const handleTabChange = (next: TicketTab) => {
    void setTab(next);
    setPage(1);
  };

  return (
    <AccountPage
      title={t('support.my_tickets', 'تذاكر الدعم')}
      actions={
        <Link href="/support/new" className={buttonVariants({ size: 'sm' })}>
          <Plus aria-hidden="true" />
          {t('support.new_ticket', 'تذكرة جديدة')}
        </Link>
      }
    >
      <Tabs value={tab} onValueChange={(value) => handleTabChange(value as TicketTab)}>
        <TabList aria-label={t('support.my_tickets', 'تذاكر الدعم')} scrollOnPhones className="gap-4">
          {TABS.map((key) => (
            <PillTab key={key} value={key}>
              {t(`support.tabs.${key}`, key)}
            </PillTab>
          ))}
        </TabList>
        {TABS.map((key) => (
          <TabPanel key={key} value={key} className="mt-[26px]">
            <TicketsList status={key === 'all' ? undefined : key} page={page} onPageChange={setPage} />
          </TabPanel>
        ))}
      </Tabs>
    </AccountPage>
  );
}

function TicketsList({
  status,
  page,
  onPageChange,
}: {
  status?: SupportTicketStatus;
  page: number;
  onPageChange: (page: number) => void;
}) {
  const { data, isLoading, isError } = useMyTicketsQuery(
    status ? { page, per_page: PER_PAGE, status } : { page, per_page: PER_PAGE },
  );

  if (isLoading) return <PanelState loading />;
  if (isError || !data) return <PanelState loading={false} message={t('common.error', 'حدث خطأ، حاول مرة أخرى')} />;

  if (data.data.length === 0) {
    return (
      <EmptyState
        icon={<Icon icon={LifeBuoy} size="lg" />}
        title={t('support.no_tickets', 'لا توجد تذاكر في هذا التبويب')}
        description={t('support.new_ticket_sub')}
        action={
          <Link href="/support/new" className={buttonVariants({ size: 'sm' })}>
            {t('support.new_ticket', 'تذكرة جديدة')}
          </Link>
        }
        className="rounded-qb-2xl border border-qb-line bg-qb-surface py-20 shadow-qb-card"
      />
    );
  }

  return (
    <>
      <ul className="flex flex-col gap-4 qb-tablet:gap-6">
        {data.data.map((ticket) => (
          <li key={ticket.id}>
            <TicketRow ticket={ticket} />
          </li>
        ))}
      </ul>
      <Pager page={page} lastPage={data.meta.last_page} onChange={onPageChange} />
    </>
  );
}
