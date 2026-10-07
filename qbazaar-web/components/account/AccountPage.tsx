import type { ReactNode } from 'react';

import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import { pageGutter } from '@/components/design-system/page-gutter';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

export interface AccountPageProps {
  title: string;
  /** Controls on the end side of the title ("Mark all as read", "Clear"). */
  actions?: ReactNode;
  children: ReactNode;
  /** Overrides the title size, e.g. the 44 px of the wishlist and saved searches (376:8322, 381:8815). */
  titleClassName?: string;
  className?: string;
}

/** Width, gutters and top spacing of the full-width account pages, under the breadcrumb band. */
export const accountPageClass = cn(
  'mx-auto w-full max-w-[1440px] pt-9 pb-16 font-qb text-qb-ink qb-tablet:pt-[72px] qb-desktop:pt-[65px] qb-desktop:pb-24',
  pageGutter,
);

/**
 * Page shell of My Ads, the wishlist, saved searches and notifications
 * (518:20536, 376:8322): breadcrumb, then the 48 px title (32 on tablets,
 * 28 without the breadcrumb on phones), then the content.
 */
export function AccountPage({ title, actions, children, titleClassName, className }: AccountPageProps) {
  return (
    <div className={cn(accountPageClass, className)}>
      <Breadcrumb items={[{ label: t('home.breadcrumb'), href: '/' }, { label: title }]} className="hidden qb-tablet:block" />
      <div className="flex flex-wrap items-center justify-between gap-4 qb-tablet:mt-[73px] qb-desktop:mt-[65px]">
        <h1
          className={cn(
            'text-qb-h2 leading-none font-semibold tracking-normal text-qb-ink qb-tablet:text-[32px] qb-desktop:text-qb-h1',
            titleClassName,
          )}
        >
          {title}
        </h1>
        {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
      </div>
      <div className="mt-[46px] qb-tablet:mt-12 qb-desktop:mt-16">{children}</div>
    </div>
  );
}

/** Type of the notifications pill tabs (16 px on phones, 20 px from tablets), which My Ads and the tickets reuse. */
export const pillTabClass = 'text-qb-body qb-tablet:text-qb-h5';

/** Pill tabs scroll sideways on phones instead of wrapping, as in 597:27743. */
export const scrollingTabListClass =
  'max-qb-tablet:-mx-4 max-qb-tablet:flex-nowrap max-qb-tablet:overflow-x-auto max-qb-tablet:px-4 max-qb-tablet:py-2 max-qb-tablet:[scrollbar-width:none]';
