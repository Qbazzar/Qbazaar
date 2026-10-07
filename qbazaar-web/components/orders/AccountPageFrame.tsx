import type { ReactNode } from 'react';

import { Breadcrumb, type BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import { pageGutter } from '@/components/design-system/page-gutter';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

/** Page width and the shared side gutters of the frames (16 px on phones, 30 on tablets, 40 at 1440). */
export const pageFrame = cn('mx-auto w-full max-w-[1440px]', pageGutter);

export interface AccountPageFrameProps {
  /** Trail after "Home"; the last item is this page. */
  breadcrumb: BreadcrumbItem[];
  /** Large page title; leave it out when the content brings its own heading. */
  title?: string;
  /** Short line under the title. */
  description?: string;
  /** Buttons or links on the end side of the title. */
  actions?: ReactNode;
  children: ReactNode;
}

/**
 * Full-width page of the sales overview (502:21437, 573:33241, 600:30639):
 * the breadcrumb (hidden on phones), the large title with its actions, then
 * the content.
 */
export function AccountPageFrame({ breadcrumb, title, description, actions, children }: AccountPageFrameProps) {
  return (
    <div className={cn(pageFrame, 'pt-8 pb-16 font-qb text-qb-ink qb-tablet:pt-[72px] qb-desktop:pt-[65px] qb-desktop:pb-24')}>
      <Breadcrumb items={[{ label: t('home.breadcrumb'), href: '/' }, ...breadcrumb]} className="hidden qb-tablet:block" />
      {title ? (
        <div className="flex flex-wrap items-center justify-between gap-4 qb-tablet:mt-[60px] qb-desktop:mt-20">
          <div className="min-w-0">
            <h1 className="font-qb text-qb-h2 leading-tight font-semibold tracking-normal text-qb-ink qb-tablet:text-[32px] qb-desktop:text-qb-h1">
              {title}
            </h1>
            {description ? (
              <p dir="auto" className="mt-2 text-start text-qb-caption font-medium text-qb-ink-subtle">
                {description}
              </p>
            ) : null}
          </div>
          {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
        </div>
      ) : null}
      <div className={cn('flex flex-col gap-6', title ? 'mt-6 qb-tablet:mt-8 qb-desktop:mt-[50px]' : 'qb-tablet:mt-8 qb-desktop:mt-12')}>
        {children}
      </div>
    </div>
  );
}
