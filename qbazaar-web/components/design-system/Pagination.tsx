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

const cell =
  'inline-flex size-10 items-center justify-center rounded-qb-md border border-qb-line bg-qb-surface px-1.5 font-qb text-qb-body-sm text-qb-ink-body';

export interface PaginationProps {
  page: number;
  totalPages: number;
  /** URL of a page, so every page is a real, crawlable link. */
  getHref: (page: number) => string;
  className?: string;
}

export function Pagination({ page, totalPages, getHref, className }: PaginationProps) {
  if (totalPages <= 1) return null;
  const current = Math.min(Math.max(1, page), totalPages);
  const atStart = current === 1;
  const atEnd = current === totalPages;

  return (
    <nav aria-label={t('ui.pagination.label')} className={cn('flex justify-center', className)}>
      <ul className="flex flex-wrap items-center gap-2">
        <Step href={getHref(1)} disabled={atStart} label={t('ui.pagination.first')} icon={ChevronsLeft} />
        <Step href={getHref(current - 1)} disabled={atStart} label={t('ui.pagination.previous')} icon={ChevronLeft} />
        {paginationRange(current, totalPages).map((number) => (
          <li key={number}>
            <Link
              href={getHref(number)}
              aria-label={t('ui.pagination.page', { page: number })}
              aria-current={number === current ? 'page' : undefined}
              className={cn(cell, focusRing, number === current ? 'bg-qb-brand text-qb-on-brand' : 'hover:bg-qb-hover')}
            >
              {number}
            </Link>
          </li>
        ))}
        <Step href={getHref(current + 1)} disabled={atEnd} label={t('ui.pagination.next')} icon={ChevronRight} />
        <Step href={getHref(totalPages)} disabled={atEnd} label={t('ui.pagination.last')} icon={ChevronsRight} />
      </ul>
    </nav>
  );
}

interface StepProps {
  href: string;
  disabled: boolean;
  label: string;
  icon: LucideIcon;
}

function Step({ href, disabled, label, icon }: StepProps): ReactNode {
  const glyph = <Icon icon={icon} size="sm" flipInRtl />;
  if (disabled) {
    return (
      <li aria-hidden="true" className={cn(cell, 'opacity-50')}>
        {glyph}
      </li>
    );
  }
  return (
    <li>
      <Link href={href} aria-label={label} className={cn(cell, focusRing, 'hover:bg-qb-hover')}>
        {glyph}
      </Link>
    </li>
  );
}
