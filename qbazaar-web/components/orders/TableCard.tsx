import type { ReactNode } from 'react';

import { Button } from '@/components/design-system/Button';
import { LoaderCircleIcon } from '@/components/design-system/icons';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { panelClass } from './CheckoutPanel';

/** The two transaction cards of the reference: the sales overview's and the wallet panel's. */
export type TableTone = 'sales' | 'wallet';

const CARD: Record<TableTone, { card: string; head: string; title: string; padded: string; inset: string }> = {
  /** sales-overview.html: r20, 28 px by 18 to 32 px padding, the title 4 px over a divider 20 px below. */
  sales: {
    card: 'rounded-[20px] shadow-qb-card-soft',
    head: 'gap-3 px-[clamp(18px,3vw,32px)] pt-7 pb-5',
    title: 'mb-1 text-qb-ink',
    padded: 'px-[clamp(18px,3vw,32px)] py-7',
    inset: 'px-[clamp(18px,3vw,32px)] pb-7',
  },
  /**
   * wallet.html `.qb-wtable`: r16 with a 1 px border, 26 px by 28 px padding, the title's 20 px bottom margin over the
   * rows; an action beside it keeps the same margin, so the two stay centred on one line.
   */
  wallet: {
    card: 'rounded-qb-xl border border-qb-line',
    head: 'gap-x-3 px-7 pt-[26px] *:mb-5',
    title: 'text-qb-ink-title',
    padded: 'px-7 py-[26px]',
    inset: 'px-7 pb-[26px]',
  },
};

export interface TableCardProps {
  title: ReactNode;
  titleId: string;
  /** Control on the end side of the title, e.g. a filter or a button. */
  action?: ReactNode;
  /** Pads the content in line with the title and divides it like the tables, for a form. */
  padded?: boolean;
  /**
   * Keeps the card's side and bottom padding around a table, so its row dividers stop at the padding as on
   * wallet.html `.qb-wtable` and sales-overview.html. Pair it with `insetTableClasses` or `salesTableClasses`.
   */
  inset?: boolean;
  tone?: TableTone;
  children: ReactNode;
  className?: string;
}

/** The "Transaction History" card that holds a table, a list or, `padded`, a form; its 24 px title at every width. */
export function TableCard({ title, titleId, action, padded = false, inset = false, tone = 'wallet', children, className }: TableCardProps) {
  const look = CARD[tone];
  const body = padded ? (
    <div className={cn('border-t border-qb-line', look.padded)}>{children}</div>
  ) : inset ? (
    <div className={look.inset}>{children}</div>
  ) : (
    children
  );
  return (
    <section aria-labelledby={titleId} className={cn(panelClass, look.card, className)}>
      <div className={cn('flex flex-wrap items-center justify-between', look.head)}>
        <h2 id={titleId} className={cn('font-qb text-qb-h3 font-semibold tracking-normal', look.title)}>
          {title}
        </h2>
        {action}
      </div>
      {body}
    </section>
  );
}

const walletTh = 'px-3 pt-0 pb-3.5 text-start text-qb-body font-medium whitespace-nowrap text-(--color-qb-ink-table) max-qb-tablet:py-0';
const walletTd = 'px-3 py-[18px] align-middle text-qb-caption font-medium text-(--color-qb-ink-table) qb-tablet:text-qb-body';

/**
 * Header cell, data cell and row classes of the wallet panel's table (wallet.html `.qb-wrow`):
 * 16 px medium #8E8E8E headers and cells, #221122 amounts, the item's 16 px semibold #333 title
 * and its 15 px #999 sub line (#A4ADBA on phones).
 */
export const tableClasses = {
  table: 'w-full border-collapse text-start',
  headRow: 'border-b border-(--color-qb-menu-divider) max-qb-tablet:border-b-0',
  th: `${walletTh} first:ps-7 last:pe-7`,
  row: 'border-b border-(--color-qb-menu-divider) last:border-b-0',
  td: `${walletTd} first:ps-7 last:pe-7`,
  /** Columns the phone layout folds into the first and last cells. */
  wide: 'hidden qb-tablet:table-cell',
  amount: 'text-end font-semibold whitespace-nowrap text-qb-ink max-qb-tablet:text-qb-body-sm qb-tablet:text-(--color-qb-ink-amount)',
  /** The item's title in the first cell. */
  itemTitle: 'font-semibold text-qb-ink-title qb-tablet:text-qb-body',
  /** The line under the item's title. */
  itemMeta: 'mt-1 text-qb-micro font-normal text-qb-breadcrumb qb-tablet:text-qb-body-sm qb-tablet:text-(--color-qb-ink-meta)',
} as const;

/** The wallet table inside an `inset` card: its outer cells sit on the card's padding instead of adding their own. */
export const insetTableClasses = {
  ...tableClasses,
  th: `${walletTh} first:ps-0 last:pe-0`,
  td: `${walletTd} first:ps-0 last:pe-0`,
} as const;

/**
 * The same for the sales overview (sales-overview.html): 15 px #9E9E9E headers, 15 px #757575
 * cells, the 16 px semibold title over a 14 px #9E9E9E line and 16 px bold amounts.
 */
export const salesTableClasses = {
  ...tableClasses,
  headRow: 'border-t border-qb-line max-qb-tablet:border-t-0',
  th: 'px-3 pt-5 pb-4 text-start text-qb-body-sm font-normal whitespace-nowrap text-qb-ink-subtle first:ps-0 last:pe-0 max-qb-tablet:py-0',
  row: 'border-t border-qb-line',
  td: 'px-3 py-[18px] align-middle text-qb-caption text-qb-ink-muted first:ps-0 last:pe-0 qb-tablet:text-qb-body-sm',
  amount: 'text-end font-bold whitespace-nowrap text-qb-ink max-qb-tablet:text-qb-body-sm qb-tablet:text-qb-body',
  itemTitle: 'font-semibold text-qb-ink qb-tablet:text-qb-body',
  itemMeta: 'mt-0.5 text-qb-micro font-normal text-qb-ink-subtle qb-tablet:text-qb-caption',
} as const;

/**
 * Column header. The phone lists have no header line (600:30639, 613:31879),
 * so under 601 px the text is only read out.
 */
export function Th({ children, className, classes = tableClasses }: { children: ReactNode; className?: string; classes?: { th: string } }) {
  return (
    <th scope="col" className={cn(classes.th, className)}>
      <span className="max-qb-tablet:sr-only">{children}</span>
    </th>
  );
}

/** "Load more" under a cursor-paginated list. */
export function LoadMore({ onClick, loading }: { onClick: () => void; loading: boolean }) {
  return (
    <div className="flex justify-center border-t border-qb-line p-4">
      <Button variant="outline" size="sm" onClick={onClick} disabled={loading} aria-busy={loading}>
        {loading ? <LoaderCircleIcon className="animate-spin motion-reduce:animate-none" aria-hidden="true" /> : null}
        {t('orders.common.load_more')}
      </Button>
    </div>
  );
}
