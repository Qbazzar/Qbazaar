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

/** "876,340 Ads • 10 Sub Category • +3,840 Today" line under the page title. */
export function CatalogStats({ items, className }: { items: CatalogStat[]; className?: string }) {
  if (!items.length) return null;
  return (
    <p
      className={cn(
        'flex flex-wrap items-center gap-x-2.5 gap-y-1 font-qb text-qb-caption text-qb-breadcrumb qb-tablet:text-qb-body qb-desktop:text-qb-h5',
        className,
      )}
    >
      {items.map((item, index) => (
        <Fragment key={item.label}>
          {index > 0 ? <span aria-hidden="true">•</span> : null}
          <span>
            <span className={cn('font-medium', item.positive ? 'text-qb-success' : 'text-qb-ink-body')}>{item.value}</span>{' '}
            {item.label}
          </span>
        </Fragment>
      ))}
    </p>
  );
}

export interface CatalogHeaderProps {
  title: ReactNode;
  breadcrumb?: BreadcrumbItem[];
  /** Usually `<CatalogStats>`; reserve its height while it loads. */
  stats?: ReactNode;
  /** Shown beside the title on desktop only; tablets and phones put them in the toolbar. */
  actions?: ReactNode;
  /** Makes the title focusable from script, to receive the focus after a filter change. */
  titleRef?: Ref<HTMLHeadingElement>;
  className?: string;
}

/**
 * Breadcrumb, title and counters of the catalog pages (185:6576, 69:467). The
 * phone frames drop the breadcrumb.
 */
export function CatalogHeader({ title, breadcrumb, stats, actions, titleRef, className }: CatalogHeaderProps) {
  return (
    <header className={cn('font-qb', className)}>
      {breadcrumb?.length ? <Breadcrumb items={breadcrumb} className="mb-[73px] hidden qb-tablet:block qb-desktop:mb-[49px]" /> : null}
      <div className="flex items-center justify-between gap-6">
        <div className="min-w-0">
          <h1
            ref={titleRef}
            tabIndex={titleRef ? -1 : undefined}
            className="font-qb text-qb-h2 leading-none font-semibold tracking-normal break-words text-qb-ink qb-tablet:text-[32px] qb-desktop:text-qb-h1"
          >
            {title}
          </h1>
          {stats ? <div className="mt-[19px] min-h-5 qb-tablet:mt-5 qb-tablet:min-h-6 qb-desktop:mt-[37px] qb-desktop:min-h-[30px]">{stats}</div> : null}
        </div>
        {actions ? <div className="hidden shrink-0 items-center gap-4 qb-desktop:flex">{actions}</div> : null}
      </div>
    </header>
  );
}
