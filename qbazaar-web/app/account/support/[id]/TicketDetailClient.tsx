'use client';

/**
 * One support ticket — no frame of its own, so it follows the closest
 * reference (DESIGN-MAP): the chat thread of 365:14788. The header carries
 * the subject, status and category, the thread is the ticket's messages and
 * the reply box sits where the chat input is. Auth-gated by /account/layout.
 */
import type { ReactNode } from 'react';
import Link from 'next/link';
import { ArrowLeft, LifeBuoy } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { Breadcrumb, type BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import { EmptyState } from '@/components/design-system/EmptyState';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { accountPageClass } from '@/components/account/AccountPage';
import { formatLongDate } from '@/components/account/format';
import { PanelState } from '@/components/account/PanelState';
import { TicketPriorityPill } from '@/components/support/TicketPriorityPill';
import { TicketReplyForm } from '@/components/support/TicketReplyForm';
import { TicketStatusPill } from '@/components/support/TicketStatusPill';
import { TicketTimeline } from '@/components/support/TicketTimeline';
import { SUPPORT_ERROR, useTicketQuery } from '@/lib/queries/support';
import { ApiClientError } from '@/lib/api/auth';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

const TICKETS_PATH = '/account/support';

export function TicketDetailClient({ id }: { id: string }) {
  const { data: ticket, isLoading, isError, error } = useTicketQuery(id);

  if (isLoading) {
    return (
      <TicketPage>
        <PanelState loading />
      </TicketPage>
    );
  }

  if (isError || !ticket) {
    const notFound =
      error instanceof ApiClientError &&
      (error.code === SUPPORT_ERROR.notFound || error.code === SUPPORT_ERROR.forbidden);
    return (
      <TicketPage>
        <EmptyState
          icon={<Icon icon={LifeBuoy} size="lg" />}
          title={
            notFound
              ? t('support.errors.ticket_not_found', 'لم نعثر على هذه التذكرة')
              : t('common.error', 'حدث خطأ، حاول مرة أخرى')
          }
          action={
            <Link href={TICKETS_PATH} className={buttonVariants({ variant: 'outline', size: 'sm' })}>
              {t('support.back_to_list', 'العودة إلى تذاكري')}
            </Link>
          }
          className="rounded-qb-2xl border border-qb-line bg-qb-surface py-20 shadow-qb-card"
        />
      </TicketPage>
    );
  }

  return (
    <TicketPage subject={ticket.subject}>
      <article className="overflow-hidden rounded-qb-2xl border border-qb-line bg-qb-surface shadow-qb-card">
        <header className="flex items-start gap-3 border-b border-qb-line px-[17px] py-4 qb-tablet:px-8 qb-tablet:py-6">
          <Link
            href={TICKETS_PATH}
            aria-label={t('support.back_to_list', 'العودة إلى تذاكري')}
            className={cn(
              '-ms-2 inline-flex size-10 shrink-0 items-center justify-center rounded-qb-md text-qb-ink hover:bg-qb-fill qb-tablet:hidden',
              focusRing,
            )}
          >
            <Icon icon={ArrowLeft} size="lg" flipInRtl />
          </Link>
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
              <h1 className="min-w-0 text-qb-body-lg font-semibold tracking-normal break-words text-qb-ink qb-tablet:text-qb-h5 qb-desktop:text-qb-h3">
                <bdi>{ticket.subject}</bdi>
              </h1>
              <TicketStatusPill status={ticket.status} />
              <TicketPriorityPill priority={ticket.priority} />
            </div>
            <p className="mt-2 text-qb-micro text-qb-ink-subtle qb-tablet:text-qb-caption">
              {t(`support.categories.${ticket.category}`, ticket.category)}
              <span aria-hidden="true"> • </span>
              <time dateTime={ticket.created_at}>{formatLongDate(ticket.created_at)}</time>
            </p>
          </div>
        </header>

        <TicketTimeline ticket={ticket} />

        <TicketReplyForm ticketId={ticket.id} status={ticket.status} />
      </article>
    </TicketPage>
  );
}

/** Account page frame with the "Home > Support tickets > subject" trail. */
function TicketPage({ subject, children }: { subject?: string; children: ReactNode }) {
  const trail: BreadcrumbItem[] = [
    { label: t('home.breadcrumb'), href: '/' },
    { label: t('support.my_tickets', 'تذاكر الدعم'), href: subject ? TICKETS_PATH : undefined },
  ];
  if (subject) trail.push({ label: subject });

  return (
    <div className={accountPageClass}>
      {/* The loaded ticket's subject is the page heading; until then the page still has one. */}
      {subject ? null : <h1 className="sr-only">{t('support.my_tickets', 'تذاكر الدعم')}</h1>}
      <Breadcrumb items={trail} className="hidden qb-tablet:block" />
      <div className="qb-tablet:mt-12 qb-desktop:mt-16">{children}</div>
    </div>
  );
}
