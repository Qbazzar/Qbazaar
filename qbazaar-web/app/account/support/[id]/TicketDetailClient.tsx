'use client';

/**
 * Ticket detail — auth-gated by the parent /account/layout.
 *
 * Layout: header (subject + status + priority + meta), the full chronological
 * timeline, then the reply form (which locks itself for terminal statuses).
 */
import Link from 'next/link';
import { ArrowLeftIcon, ArrowRightIcon, Loader2Icon } from 'lucide-react';

import { TicketStatusPill } from '@/components/support/TicketStatusPill';
import { TicketPriorityPill } from '@/components/support/TicketPriorityPill';
import { TicketReplyForm } from '@/components/support/TicketReplyForm';
import { TicketTimeline } from '@/components/support/TicketTimeline';
import { useTicketQuery } from '@/lib/queries/support';
import { ApiClientError } from '@/lib/api/auth';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

interface Props {
  id: string;
}

export function TicketDetailClient({ id }: Props) {
  const locale = getLocale();
  const Back = locale === 'ar' ? ArrowRightIcon : ArrowLeftIcon;
  const { data: ticket, isLoading, isError, error } = useTicketQuery(id);

  if (isLoading) {
    return (
      <div className="flex min-h-[50svh] items-center justify-center" role="status">
        <Loader2Icon
          className="text-muted-foreground size-6 animate-spin"
          aria-hidden
        />
      </div>
    );
  }

  if (isError || !ticket) {
    const notFound =
      error instanceof ApiClientError &&
      (error.code === 'TICKET_NOT_FOUND' || error.code === 'TICKET_FORBIDDEN');
    return (
      <div className="card card--lg text-center">
        <h1 className="text-h3 text-ink-900">
          {notFound
            ? t('support.errors.ticket_not_found', 'لم نعثر على هذه التذكرة')
            : t('common.error', 'حدث خطأ، حاول مرة أخرى')}
        </h1>
        <Link className="text-coral mt-3 inline-block text-sm underline" href="/account/support">
          {t('support.back_to_list', 'العودة إلى تذاكري')}
        </Link>
      </div>
    );
  }

  return (
    <div style={{ paddingBottom: 32 }}>
      <Link
        href="/account/support"
        className="text-ink-700 hover:text-coral mb-4 inline-flex items-center gap-1.5 text-sm"
      >
        <Back className="size-4" aria-hidden />
        {t('support.back_to_list', 'العودة إلى تذاكري')}
      </Link>

      <header className="card card--lg mb-6">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <h1 className="text-h3 text-ink-900 min-w-0 flex-1 break-words">
            {ticket.subject}
          </h1>
          <div className="flex shrink-0 flex-wrap items-center gap-2">
            <TicketStatusPill status={ticket.status} />
            <TicketPriorityPill priority={ticket.priority} />
          </div>
        </div>
        <p className="text-ink-500 mt-2 text-xs">
          {t(`support.categories.${ticket.category}`, ticket.category)} ·{' '}
          {new Date(ticket.created_at).toLocaleString()}
        </p>
      </header>

      <TicketTimeline ticket={ticket} />

      <TicketReplyForm ticketId={ticket.id} status={ticket.status} />
    </div>
  );
}
