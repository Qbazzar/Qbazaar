import type { ReactNode } from 'react';
import Link from 'next/link';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, type LucideIcon } from 'lucide-react';

import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { focusRing } from './focus-ring';
import { Icon } from './Icon';

const VISIBLE_PAGES = 5;

/** Up to five consecutive page numbers around `page`, clamped to 1..total. */
export function paginationRange(page: number, total: number, visible = VISIBLE_PAGES): number[] {
  const count = Math.min(visible, total);
  const start = Math.min(Math.max(1, page - Math.floor(count / 2)), total - count + 1);
  return Array.from({ length: count }, (_, i) => start + i);
}

const cellBase =
  'inline-flex items-center justify-center rounded-qb-md border border-qb-line bg-qb-surface px-1.5 font-qb text-qb-ink-body';

/** The reference draws two pagers. */
const SIZES = {
  /** category.html: 40 px cells 8 px apart, 15 px numbers, the current page orange inside the grey border. */
  md: { list: 'gap-2', cell: 'h-10 min-w-10 text-qb-body-sm', current: '', step: '' },
  /** companies.html: 44 px cells 12 px apart, 16 px / 500 numbers, an orange border on the current page, grey arrows. */
  lg: { list: 'gap-3', cell: 'h-11 min-w-11 text-qb-body font-medium', current: 'border-qb-brand', step: 'text-qb-ink-muted' },
} as const;

export type PaginationSize = keyof typeof SIZES;

export interface PaginationProps {
  page: number;
  totalPages: number;
  /** URL of a page, so every page is a real, crawlable link. */
  getHref: (page: number) => string;
  size?: PaginationSize;
  className?: string;
}

export function Pagination({ page, totalPages, getHref, size = 'md', className }: PaginationProps) {
  if (totalPages <= 1) return null;
  const current = Math.min(Math.max(1, page), totalPages);
  const atStart = current === 1;
  const atEnd = current === totalPages;
  const look = SIZES[size];
  const stepCell = cn(cellBase, look.cell, look.step);

  return (
    <nav aria-label={t('ui.pagination.label')} className={cn('flex justify-center', className)}>
      <ul className={cn('flex flex-wrap items-center', look.list)}>
        <Step href={getHref(1)} disabled={atStart} label={t('ui.pagination.first')} icon={ChevronsLeft} className={stepCell} />
        <Step href={getHref(current - 1)} disabled={atStart} label={t('ui.pagination.previous')} icon={ChevronLeft} className={stepCell} />
        {paginationRange(current, totalPages).map((number) => (
          <li key={number}>
            <Link
              href={getHref(number)}
              aria-label={t('ui.pagination.page', { page: number })}
              aria-current={number === current ? 'page' : undefined}
              className={cn(
                cellBase,
                look.cell,
                focusRing,
                number === current ? cn('bg-qb-brand text-qb-on-brand', look.current) : 'hover:bg-qb-hover',
              )}
            >
              {number}
            </Link>
          </li>
        ))}
        <Step href={getHref(current + 1)} disabled={atEnd} label={t('ui.pagination.next')} icon={ChevronRight} className={stepCell} />
        <Step href={getHref(totalPages)} disabled={atEnd} label={t('ui.pagination.last')} icon={ChevronsRight} className={stepCell} />
      </ul>
    </nav>
  );
}

interface StepProps {
  href: string;
  disabled: boolean;
  label: string;
  icon: LucideIcon;
  className: string;
}

function Step({ href, disabled, label, icon, className }: StepProps): ReactNode {
  const glyph = <Icon icon={icon} size="sm" flipInRtl />;
  if (disabled) {
    return (
      <li aria-hidden="true" className={cn(className, 'opacity-50')}>
        {glyph}
      </li>
    );
  }
  return (
    <li>
      <Link href={href} aria-label={label} className={cn(className, focusRing, 'hover:bg-qb-hover')}>
        {glyph}
      </Link>
    </li>
  );
}
