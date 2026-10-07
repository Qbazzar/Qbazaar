import Link from 'next/link';
import { ChevronRight, Clock, MessageCircle, MessageSquareText } from 'lucide-react';

import { Icon } from '@/components/design-system/Icon';
import { formatLongDate } from '@/components/account/format';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import type { SupportTicketListItem } from '@/lib/api/types';

import { TicketPriorityPill } from './TicketPriorityPill';
import { TicketStatusPill } from './TicketStatusPill';

/**
 * One support ticket in the My Ads row style (518:20536): icon tile, subject
 * with its status chip, the category, then the replies and opening date.
 * The whole card opens the ticket.
 */
export function TicketRow({ ticket }: { ticket: SupportTicketListItem }) {
  return (
    <article className="relative flex items-center gap-4 rounded-qb-2xl border border-qb-line bg-qb-surface p-[17px] font-qb shadow-qb-card has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-qb-brand-active qb-tablet:gap-6 qb-tablet:px-6 qb-tablet:py-5">
      <span
        aria-hidden="true"
        className="hidden size-[60px] shrink-0 items-center justify-center rounded-qb-xl border border-qb-line bg-qb-surface text-qb-ink-body shadow-qb-card qb-tablet:flex"
      >
        <Icon icon={MessageSquareText} size="lg" />
      </span>

      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
          <h2 className="min-w-0 text-qb-h5 font-semibold tracking-normal break-words text-qb-ink-title qb-desktop:text-qb-h3">
            <Link
              href={`/account/support/${ticket.id}`}
              className="outline-none after:absolute after:inset-0 after:rounded-qb-2xl hover:text-qb-brand"
            >
              <bdi>{ticket.subject}</bdi>
            </Link>
          </h2>
          <TicketStatusPill status={ticket.status} />
          <TicketPriorityPill priority={ticket.priority} />
        </div>
        <p className="mt-2 text-qb-caption text-qb-ink-subtle qb-desktop:text-qb-body">
          {t(`support.categories.${ticket.category}`, ticket.category)}
        </p>
        <ul className="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-qb-caption text-qb-ink-subtle qb-desktop:text-qb-body">
          <li className="flex items-center gap-1.5">
            <Icon icon={MessageCircle} />
            {tPlural('support.replies_count', ticket.replies_count)}
          </li>
          <li className="flex items-center gap-1.5">
            <Icon icon={Clock} />
            <time dateTime={ticket.created_at}>{formatLongDate(ticket.created_at)}</time>
          </li>
        </ul>
      </div>

      <Icon icon={ChevronRight} size="lg" flipInRtl className="shrink-0 text-qb-ink-subtle" />
    </article>
  );
}
