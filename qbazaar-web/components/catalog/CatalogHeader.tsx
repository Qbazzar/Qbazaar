import { Fragment, type ReactNode, type Ref } from 'react';

import { Breadcrumb, type BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import { formatNumber } from '@/lib/i18n/format';
import type { Locale } from '@/lib/i18n/locale';
import { cn } from '@/lib/utils';

export interface CatalogStat {
  value: string;
  label: string;
  /** Today's count is green in the design. */
  positive?: boolean;
}

/** The green "+3,840 Today" counter; left out on a day without new ads. */
export function todayStat(count: number, label: string, locale: Locale): CatalogStat[] {
  return count > 0 ? [{ value: `+${formatNumber(count, locale)}`, label, positive: true }] : [];
}

interface CatalogStatsProps {
  items: CatalogStat[];
  /** "•" on the listing pages, "·" on all-categories.html. */
  separator?: '•' | '·';
  className?: string;
}

/** "876,340 Ads • 10 Sub Category • +3,840 Today": one grey 15 px line, only today's number green. */
export function CatalogStats({ items, separator = '•', className }: CatalogStatsProps) {
  if (!items.length) return null;
  return (
    <p className={cn('font-qb text-qb-body-sm text-qb-ink-subtle', className)}>
      {items.map((item, index) => (
        <Fragment key={item.label}>
          {index > 0 ? <span aria-hidden="true">{` ${separator} `}</span> : null}
          <span className={item.positive ? 'text-qb-success' : undefined}>{item.value}</span>
          {` ${item.label}`}
        </Fragment>
      ))}
    </p>
  );
}

/**
 * all-categories.html titles its page smaller (clamp 28-40 px) than the
 * listing pages (clamp 30-44 px), and sets its counters 2 px further down.
 */
const VARIANTS = {
  index: { title: 'text-[clamp(28px,4vw,40px)]', stats: 'mt-2.5' },
  listing: { title: 'text-[clamp(30px,5vw,44px)]', stats: 'mt-2' },
} as const;

export interface CatalogHeaderProps {
  title: ReactNode;
  breadcrumb?: BreadcrumbItem[];
  /** Usually `<CatalogStats>`; its line is reserved while it loads. */
  stats?: ReactNode;
  /** Beside the title from 601 px (wrapping under it on tablets); phones put them in the toolbar. */
  actions?: ReactNode;
  /** Makes the title focusable from script, to receive the focus after a filter change. */
  titleRef?: Ref<HTMLHeadingElement>;
  variant?: keyof typeof VARIANTS;
  /** all-categories.html keeps its breadcrumb on phones; the listing pages drop it there. */
  breadcrumbOnPhones?: boolean;
  className?: string;
}

/** Breadcrumb, title and counters of the catalog pages (category.html, parent-category.html, all-categories.html). */
export function CatalogHeader({ title, breadcrumb, stats, actions, titleRef, variant = 'listing', breadcrumbOnPhones = false, className }: CatalogHeaderProps) {
  const look = VARIANTS[variant];
  return (
    <header className={cn('font-qb', className)}>
      {breadcrumb?.length ? (
        <Breadcrumb items={breadcrumb} separator="guillemet" className={cn('mb-3.5', !breadcrumbOnPhones && 'hidden qb-tablet:block')} />
      ) : null}
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <h1
            ref={titleRef}
            tabIndex={titleRef ? -1 : undefined}
            className={cn('font-qb leading-normal font-semibold tracking-normal break-words text-qb-ink', look.title)}
          >
            {title}
          </h1>
          {stats ? <div className={cn('min-h-[22.5px]', look.stats)}>{stats}</div> : null}
        </div>
        {actions ? <div className="hidden shrink-0 items-center gap-3 qb-tablet:flex">{actions}</div> : null}
      </div>
    </header>
  );
}
