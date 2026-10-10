import { useId, type ReactNode } from 'react';
import Image from 'next/image';
import { Handshake, Package, Store } from 'lucide-react';

import { Icon } from '@/components/design-system/Icon';
import type { DealCardAd } from '@/lib/api/commerce-types';
import { formatDateTime, isoDate } from '@/lib/orders/dates';
import type { StatusTone } from '@/lib/orders/status';
import { cn } from '@/lib/utils';

import { StatusPill } from './StatusPill';

/** The state line is a tinted box with centred text, as in 721:41875 and 721:42157. */
const OUTCOME_TONE: Record<StatusTone, string> = {
  success: 'bg-qb-success-soft text-qb-success',
  danger: 'bg-qb-danger-soft text-qb-danger',
  brand: 'bg-qb-brand-soft text-qb-brand-on-soft',
  info: 'bg-qb-info-soft text-qb-info',
  neutral: 'bg-qb-fill text-qb-ink-secondary',
};

export interface DealCardProps {
  kind: 'purchase' | 'offer';
  title: string;
  status: { tone: StatusTone; label: string };
  ad: DealCardAd | null;
  /** The headline amount, already formatted. */
  price: string;
  /** Secondary amount line, e.g. "2 × QAR 775.00". */
  priceNote?: string;
  createdAt: string;
  note?: { label: string; text: string } | null;
  /** One line saying where the deal stands, shown under the content. */
  outcome?: { tone: StatusTone; text: string } | null;
  actions?: ReactNode;
  /** Own cards sit at the end of the thread, the other side's at the start, next to the avatar. */
  align: 'start' | 'end';
}

/**
 * Frame of the purchase-request and offer cards in the chat (667:30685,
 * 667:31850): heading and status, the item with its amount and time, the
 * buyer's message, then the state line and the actions for the viewer.
 */
export function DealCard({
  kind,
  title,
  status,
  ad,
  price,
  priceNote,
  createdAt,
  note,
  outcome,
  actions,
  align,
}: DealCardProps) {
  const headingId = useId();

  return (
    <div className={cn('flex w-full', align === 'end' ? 'justify-end' : 'justify-start')}>
      <article
        aria-labelledby={headingId}
        className={cn(
          'w-full max-w-[534px] rounded-qb-md border border-qb-line bg-qb-surface p-4 font-qb shadow-qb-card',
          align === 'end' ? 'rounded-ee-none' : 'rounded-ss-none',
        )}
      >
        <header className="flex items-center justify-between gap-3">
          <h2
            id={headingId}
            className="flex min-w-0 items-center gap-1 font-qb text-qb-body leading-6 font-medium tracking-normal text-qb-ink"
          >
            <Icon icon={kind === 'offer' ? Handshake : Store} size="sm" className="size-[18px] text-qb-brand" />
            <span className="truncate">{title}</span>
          </h2>
          <StatusPill tone={status.tone} className="qb-tablet:text-qb-micro">
            {status.label}
          </StatusPill>
        </header>

        <div className="mt-4 flex flex-wrap items-center gap-x-2.5 gap-y-2 border-t border-qb-line pt-4">
          <div className="relative h-[58px] w-[63px] shrink-0 overflow-hidden rounded-qb-sm bg-qb-fill">
            {ad?.thumbUrl ? (
              <Image src={ad.thumbUrl} alt="" fill sizes="63px" className="object-cover" />
            ) : (
              <span className="flex size-full items-center justify-center text-qb-ink-subtle">
                <Icon icon={Package} />
              </span>
            )}
          </div>
          <div className="min-w-0 flex-1">
            {ad ? (
              <p className="truncate text-qb-body font-medium text-qb-ink">
                <bdi>{ad.title}</bdi>
              </p>
            ) : null}
            <p className="mt-1 text-qb-caption font-semibold text-qb-brand">{price}</p>
            {priceNote ? <p className="mt-0.5 text-qb-micro text-qb-ink-subtle">{priceNote}</p> : null}
          </div>
          <time
            dateTime={isoDate(createdAt)}
            className="basis-full text-qb-micro text-qb-ink-subtle qb-tablet:basis-auto qb-tablet:self-start qb-tablet:pt-5"
          >
            {formatDateTime(createdAt)}
          </time>
        </div>

        {note ? (
          <div className="mt-4 border-t border-qb-line pt-4">
            <div className="relative rounded-e-qb-sm border border-qb-line py-2.5 ps-3 pe-2.5 before:absolute before:inset-y-0 before:start-0 before:w-0.5 before:rounded-qb-pill before:bg-qb-brand">
              <p className="text-qb-micro text-qb-ink-subtle">{note.label}</p>
              <p dir="auto" className="mt-2 text-start text-qb-label break-words whitespace-pre-wrap text-qb-ink">
                {note.text}
              </p>
            </div>
          </div>
        ) : null}

        {outcome ? (
          <p className={cn('mt-4 rounded-qb-sm px-3 py-2.5 text-center text-qb-caption font-medium', OUTCOME_TONE[outcome.tone])}>
            {outcome.text}
          </p>
        ) : null}

        {actions ? <div className="mt-[18px] flex flex-wrap gap-2 [&>*]:min-w-[7.5rem] [&>*]:flex-1">{actions}</div> : null}
      </article>
    </div>
  );
}
