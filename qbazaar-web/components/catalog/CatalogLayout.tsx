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

/** Header, then the filter sidebar beside the toolbar and results (18:912, 69:467, 492:20208). */
export function CatalogLayout({ header, sidebar, toolbar, toolbarBelowDesktopOnly = false, children }: CatalogLayoutProps) {
  return (
    <main className={cn('bg-qb-page font-qb text-qb-ink', headingFont)}>
      <div className={cn(siteFrame, catalogPageTop)}>
        {header}
        <div className="mt-11 flex items-start gap-9 qb-tablet:mt-[49px]">
          {sidebar}
          <div className="min-w-0 flex-1">
            {toolbar ? <div className={cn('mb-8', toolbarBelowDesktopOnly && 'qb-desktop:hidden')}>{toolbar}</div> : null}
            {children}
          </div>
        </div>
      </div>
    </main>
  );
}
