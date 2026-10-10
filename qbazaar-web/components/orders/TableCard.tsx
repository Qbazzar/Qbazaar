import type { ReactNode } from 'react';
import { Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { panelClass } from './CheckoutPanel';

import '@/styles/design-tokens-sell.css';

export interface TableCardProps {
  title: ReactNode;
  titleId: string;
  /** Control on the end side of the title, e.g. a filter or a button. */
  action?: ReactNode;
  /** Pads the content in line with the title and divides it like the tables, for a form. */
  padded?: boolean;
  children: ReactNode;
  className?: string;
}

/** The "Transaction History" card (502:21437) that holds a table, a list or, `padded`, a form. */
export function TableCard({ title, titleId, action, padded = false, children, className }: TableCardProps) {
  return (
    <section aria-labelledby={titleId} className={cn(panelClass, className)}>
      <div className="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 pb-4 qb-tablet:px-[18px] qb-desktop:px-8 qb-desktop:pt-8 qb-desktop:pb-6">
        <h2
          id={titleId}
          className="font-qb text-qb-body-lg font-semibold tracking-normal text-qb-ink-title qb-tablet:text-qb-body qb-desktop:text-qb-h3"
        >
          {title}
        </h2>
        {action}
      </div>
      {padded ? <div className="border-t border-qb-line p-5 qb-tablet:px-[18px] qb-desktop:p-8">{children}</div> : children}
    </section>
  );
}

/**
 * Header cell, data cell and row classes shared by the order and wallet
 * tables, in the type of 502:21437 and 502:22401: #8E8E8E headers and cells,
 * #221122 amounts; the item's title and its #999 sub line (#A4ADBA on phones).
 */
export const tableClasses = {
  table: 'w-full border-collapse text-start',
  headRow: 'border-y border-qb-line max-qb-tablet:border-b-0',
  th: 'px-3 py-4 text-start text-qb-caption font-medium whitespace-nowrap text-(--color-qb-ink-table) first:ps-5 last:pe-5 max-qb-tablet:py-0 qb-desktop:text-qb-body qb-desktop:first:ps-8 qb-desktop:last:pe-8',
  row: 'border-b border-qb-line last:border-b-0',
  td: 'px-3 py-4 align-middle text-qb-caption font-medium text-(--color-qb-ink-table) first:ps-5 last:pe-5 qb-desktop:text-qb-body qb-desktop:first:ps-8 qb-desktop:last:pe-8',
  /** Columns the phone layout folds into the first and last cells. */
  wide: 'hidden qb-tablet:table-cell',
  amount: 'text-end font-semibold whitespace-nowrap text-qb-ink max-qb-tablet:text-qb-body-sm qb-tablet:text-(--color-qb-ink-amount)',
  /** The item's title in the first cell. */
  itemTitle: 'font-medium text-qb-ink qb-tablet:font-semibold qb-tablet:text-qb-ink-title',
  /** The line under the item's title. */
  itemMeta: 'mt-0.5 text-qb-micro font-normal text-qb-breadcrumb qb-tablet:text-(--color-qb-ink-meta) qb-desktop:text-qb-body',
} as const;

/**
 * Column header. The phone lists have no header line (600:30639, 613:31879),
 * so under 601 px the text is only read out.
 */
export function Th({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <th scope="col" className={cn(tableClasses.th, className)}>
      <span className="max-qb-tablet:sr-only">{children}</span>
    </th>
  );
}

/** "Load more" under a cursor-paginated list. */
export function LoadMore({ onClick, loading }: { onClick: () => void; loading: boolean }) {
  return (
    <div className="flex justify-center border-t border-qb-line p-4">
      <Button variant="outline" size="sm" onClick={onClick} disabled={loading} aria-busy={loading}>
        {loading ? <Loader2 className="animate-spin motion-reduce:animate-none" aria-hidden="true" /> : null}
        {t('orders.common.load_more')}
      </Button>
    </div>
  );
}
