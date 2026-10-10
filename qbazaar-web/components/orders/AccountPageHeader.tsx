import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

import '@/styles/design-tokens-sell.css';

export interface AccountPageHeaderProps {
  title: string;
  description?: string;
  /** Buttons or links on the end side (wrap under the title on phones). */
  actions?: ReactNode;
  className?: string;
}

/**
 * Heading of the wallet panel (502:22401), which sits in the account
 * settings column: title, a short description and the page's actions.
 */
export function AccountPageHeader({ title, description, actions, className }: AccountPageHeaderProps) {
  return (
    <header className={cn('font-qb', className)}>
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0 flex-1">
          <h1 className="font-qb text-qb-h5 font-semibold tracking-normal text-(--color-qb-ink-panel)">{title}</h1>
          {/* 12 px under 1001 px (563:31406, 613:31879), 14 px on desktop (502:22401). */}
          {description ? <p className="mt-2 text-qb-micro font-medium text-qb-ink-subtle qb-desktop:text-qb-caption">{description}</p> : null}
        </div>
        {actions ? <div className="flex shrink-0 flex-wrap gap-2">{actions}</div> : null}
      </div>
    </header>
  );
}

/** The white bordered header button; phones show its icon only, so put the text in a `HeaderButtonLabel`. */
const headerButtonBase =
  'size-11 gap-2 rounded-qb-md border border-qb-line bg-qb-surface px-0 font-normal shadow-qb-card hover:bg-qb-hover qb-tablet:w-auto [&_svg]:size-5';

/**
 * "Payment Method" / "Withdraw Funds" of the wallet panel header: an icon on
 * phones (613:31879), a compact 12 px button on tablets (563:31406), 16 px
 * from 1001 px (502:22401), in #212121.
 */
export const headerButton = cn(
  headerButtonBase,
  'text-qb-ink',
  'qb-tablet:h-[34px] qb-tablet:gap-1.5 qb-tablet:px-2.5 qb-tablet:text-qb-micro qb-tablet:[&_svg]:size-4',
  'qb-desktop:h-11 qb-desktop:gap-2 qb-desktop:px-4 qb-desktop:text-qb-body qb-desktop:[&_svg]:size-5',
);

/** The same buttons beside the large title of the sales overview: 50 px, radius 12, #333 (sales-overview.html). */
export const pageHeaderButton = cn(
  headerButtonBase,
  'text-qb-ink-title',
  'qb-tablet:h-11 qb-tablet:px-4 qb-tablet:text-qb-caption qb-desktop:h-[50px] qb-desktop:rounded-qb-lg qb-desktop:px-5 qb-desktop:text-qb-body',
);

/** Text of a `headerButton`: the button's accessible name everywhere, visible from the tablet up. */
export function HeaderButtonLabel({ children }: { children: ReactNode }) {
  return <span className="sr-only qb-tablet:not-sr-only">{children}</span>;
}
