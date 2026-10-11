import type { ReactNode } from 'react';

import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import { pageGutter } from '@/components/design-system/page-gutter';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

export interface AccountPageProps {
  title: string;
  /** Controls on the end side of the title ("Clear all"). */
  actions?: ReactNode;
  children: ReactNode;
  /** My Ads opens like the settings screen: no breadcrumb and the 40 px title. */
  variant?: 'page' | 'account';
  /** Overrides the title size, e.g. the 44 px of the wishlist (376:8322). */
  titleClassName?: string;
  className?: string;
}

/** The clamp(28–40 px) title of the saved searches and the settings screen. */
export const compactTitleClass = 'text-[clamp(28px,4vw,40px)]';

/** Width, gutters and vertical padding of the full-width account pages (`clamp(20px, 4vw, 40px)`). */
export const accountPageClass = cn(
  'mx-auto w-full max-w-[1440px] py-[clamp(20px,4vw,40px)] font-qb text-qb-ink',
  pageGutter,
);

/**
 * Page shell of notifications, saved searches and the saved lists in the
 * app template: breadcrumb, the clamp(30–48 px) title, then the content.
 */
export function AccountPage({ title, actions, children, variant = 'page', titleClassName, className }: AccountPageProps) {
  const isAccount = variant === 'account';
  return (
    <div className={cn(accountPageClass, className)}>
      {isAccount ? null : (
        <Breadcrumb items={[{ label: t('home.breadcrumb'), href: '/' }, { label: title }]} className="mb-3.5" />
      )}
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1
          className={cn(
            'leading-normal font-semibold tracking-normal text-qb-ink',
            isAccount ? compactTitleClass : 'text-[clamp(30px,5vw,48px)]',
            titleClassName,
          )}
        >
          {title}
        </h1>
        {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
      </div>
      {children}
    </div>
  );
}
