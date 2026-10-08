import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

import { Breadcrumb, type BreadcrumbItem } from './Breadcrumb';
import { siteFrame } from './site-frame';

export interface PageShellProps {
  /** The page's only h1. */
  title: ReactNode;
  /** Trail that ends with the current page. The reference shows it from tablet up. */
  breadcrumb?: BreadcrumbItem[];
  /** Summary line under the title ("60 categories · +3,840 ads today"). */
  meta?: ReactNode;
  /** End-side buttons next to the title ("Save Search"). */
  actions?: ReactNode;
  children?: ReactNode;
  className?: string;
}

/**
 * Page frame of the notifications, wishlist and categories screens
 * (455:14636, 376:7817, 185:6576): breadcrumb, 48/32/28 px title and an
 * optional summary line above the content, in the 1360 px column.
 */
export function PageShell({ title, breadcrumb, meta, actions, children, className }: PageShellProps) {
  return (
    <main className={cn('bg-qb-page font-qb text-qb-ink', className)}>
      <div className={cn(siteFrame, 'pt-9 pb-16 qb-tablet:pt-16 qb-desktop:pb-20')}>
        <header className="mb-6 qb-tablet:mb-8 qb-desktop:mb-10">
          {breadcrumb?.length ? (
            <Breadcrumb items={breadcrumb} className="mb-16 hidden qb-tablet:block qb-desktop:mb-12" />
          ) : null}
          <div className="flex flex-wrap items-end justify-between gap-4">
            <div className="min-w-0">
              <h1 className="font-qb text-qb-h2 font-semibold tracking-normal break-words text-qb-ink qb-tablet:text-[32px] qb-desktop:text-qb-h1">
                {title}
              </h1>
              {meta ? (
                <p className="mt-2 text-qb-caption text-qb-ink-subtle qb-tablet:mt-3 qb-tablet:text-qb-body qb-desktop:text-qb-h5">
                  {meta}
                </p>
              ) : null}
            </div>
            {actions ? <div className="flex shrink-0 flex-wrap gap-3">{actions}</div> : null}
          </div>
        </header>
        {children}
      </div>
    </main>
  );
}
