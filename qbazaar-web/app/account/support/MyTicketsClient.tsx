'use client';

/**
 * My tickets index — auth-gated by the parent /account/layout.
 *
 * Layout: header + nuqs-driven tab filters (`All`, `Open`, `In Progress`,
 * `Resolved`) + a list of `.ticket-row` cards. Pagination follows the same
 * pattern as the notifications page.
 */
import { useState } from 'react';
import Link from 'next/link';
import { parseAsStringEnum, useQueryState } from 'nuqs';
import { Loader2Icon, PlusIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { TicketStatusPill } from '@/components/support/TicketStatusPill';
import { TicketPriorityPill } from '@/components/support/TicketPriorityPill';
import { useMyTicketsQuery } from '@/lib/queries/support';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type {
  SupportTicketListItem,
  SupportTicketStatus,
} from '@/lib/api/types';

const PER_PAGE = 20;
type Tab = 'all' | 'open' | 'in_progress' | 'resolved';

function tabToStatus(tab: Tab): SupportTicketStatus | undefined {
  if (tab === 'all') return undefined;
  return tab;
}

export function MyTicketsClient() {
  const [tab, setTab] = useQueryState(
    'tab',
    parseAsStringEnum<Tab>(['all', 'open', 'in_progress', 'resolved']).withDefault(
      'all',
    ),
  );
  const [page, setPage] = useState(1);

  const status = tabToStatus(tab);
  const params = status
    ? { page, per_page: PER_PAGE, status }
    : { page, per_page: PER_PAGE };
  const { data, isLoading, isError } = useMyTicketsQuery(params);

  const items = data?.data ?? [];
  const lastPage = data?.meta.last_page ?? 1;

  const handleTabChange = (next: Tab) => {
    void setTab(next);
    setPage(1);
  };

  return (
    <div className="notif-page" style={{ paddingTop: 8, paddingBottom: 16 }}>
      <div className="notif-head">
        <div>
          <h1 className="notif-head__h">
            {t('support.my_tickets', 'تذاكر الدعم')}
          </h1>
          <p className="notif-head__sub">
            {t(
              'support.my_tickets_subtitle',
              'كل تذاكر الدعم الخاصة بك في مكان واحد.',
            )}
          </p>
        </div>
        <Button asChild className="bg-coral hover:bg-coral/90 rounded-full text-white">
          <Link href="/support/new">
            <PlusIcon className="size-4" aria-hidden />
            {t('support.new_ticket', 'تذكرة جديدة')}
          </Link>
        </Button>
      </div>

      <div className="notif-filters" role="tablist">
        {(['all', 'open', 'in_progress', 'resolved'] as Tab[]).map((value) => (
          <button
            key={value}
            type="button"
            role="tab"
            aria-selected={tab === value}
            onClick={() => handleTabChange(value)}
            className={cn('chip', tab === value && 'is-active')}
          >
            {t(`support.tabs.${value}`, value)}
          </button>
        ))}
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
          {t('common.error', 'حدث خطأ، حاول مرة أخرى')}
        </p>
      ) : items.length === 0 ? (
        <div className="card card--lg text-center">
          <p className="text-ink-700 text-sm">
            {t('support.no_tickets', 'لا توجد تذاكر في هذا التبويب')}
          </p>
        </div>
      ) : (
        <>
          <ul className="tickets-table">
            {items.map((ticket) => (
              <li key={ticket.id} className="list-none">
                <TicketRow ticket={ticket} />
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

function TicketRow({ ticket }: { ticket: SupportTicketListItem }) {
  return (
    <Link href={`/account/support/${ticket.id}`} className="ticket-row">
      <div className="min-w-0">
        <div className="ticket-row__subject truncate">{ticket.subject}</div>
        <div className="ticket-row__meta">
          <span>{t(`support.categories.${ticket.category}`, ticket.category)}</span>
          <span>·</span>
          <span>
            {t(
              'support.replies_count',
              { count: String(ticket.replies_count) },
              `${ticket.replies_count} replies`,
            )}
          </span>
          <span>·</span>
          <time dateTime={ticket.created_at}>
            {new Date(ticket.created_at).toLocaleDateString()}
          </time>
        </div>
      </div>
      <div className="ticket-row__side">
        <TicketStatusPill status={ticket.status} />
        <TicketPriorityPill priority={ticket.priority} />
      </div>
    </Link>
  );
}
