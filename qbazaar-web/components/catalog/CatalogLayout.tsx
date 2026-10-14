import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

import { siteFrame } from '@/components/design-system/site-frame';
import { catalogPageTop, headingFont } from './layout';

interface CatalogLayoutProps {
  header: ReactNode;
  /** Filter card, shown from 1001 px. */
  sidebar?: ReactNode;
  toolbar?: ReactNode;
  /** The category overview has no desktop toolbar: its controls live in the sidebar. */
  toolbarBelowDesktopOnly?: boolean;
  children: ReactNode;
}

/**
 * parent-category.html on phones: its Filter row (`.qb-cp-row`, margin 2px 0 4px) sits in the
 * header, 18 px under the title block and 26 px above the rows.
 */
const overviewPhoneRow = '-mt-1 mb-[26px] qb-tablet:mt-0 qb-tablet:mb-[18px]';

/**
 * Header, then the filter card beside the toolbar and results: 22 px under the
 * title row, 24 px between the card and the results, 18 px under the toolbar
 * (category.html, parent-category.html).
 */
export function CatalogLayout({ header, sidebar, toolbar, toolbarBelowDesktopOnly = false, children }: CatalogLayoutProps) {
  return (
    <main className={cn('bg-qb-page font-qb text-qb-ink', headingFont)}>
      <div className={cn(siteFrame, catalogPageTop)}>
        {header}
        <div className="mt-[22px] flex items-start gap-6">
          {sidebar}
          <div className="min-w-0 flex-1">
            {toolbar ? <div className={cn('mb-[18px]', toolbarBelowDesktopOnly && ['qb-desktop:hidden', overviewPhoneRow])}>{toolbar}</div> : null}
            {children}
          </div>
        </div>
      </div>
    </main>
  );
}
