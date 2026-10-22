import type { ReactNode } from 'react';

import { Breadcrumb, type BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import { pageGutter } from '@/components/design-system/page-gutter';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

/** Page width and the shared side gutters of the checkout frame (16 px on phones, 30 on tablets, 40 at 1440). */
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
 * Full-width page of sales-overview.html and payment-method.html: the 1440 px column with the
 * site gutter, the trail 22 px above the title (16 px on phones), the 30 to 48 px title with its
 * actions 30 px above the content.
 */
export function AccountPageFrame({ breadcrumb, title, description, actions, children }: AccountPageFrameProps) {
  return (
    <div className="mx-auto w-full max-w-[1440px] px-qb-gutter py-[clamp(20px,4vw,40px)] font-qb text-qb-ink">
      <Breadcrumb items={[{ label: t('home.breadcrumb'), href: '/' }, ...breadcrumb]} className="mb-4 qb-tablet:mb-[22px]" />
      {title ? (
        <div className="mb-[30px] flex flex-wrap items-center justify-between gap-5">
          <div className="min-w-0">
            <h1 className="text-[length:clamp(30px,5vw,48px)] leading-[normal] font-semibold tracking-normal text-qb-ink">{title}</h1>
            {description ? (
              <p dir="auto" className="mt-2 text-start text-qb-caption font-medium text-qb-ink-subtle">
                {description}
              </p>
            ) : null}
          </div>
          {actions ? <div className="flex flex-wrap gap-3.5">{actions}</div> : null}
        </div>
      ) : null}
      <div className="flex flex-col gap-6">{children}</div>
    </div>
  );
}
