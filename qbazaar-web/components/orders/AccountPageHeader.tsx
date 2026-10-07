import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

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
        <div className="min-w-0">
          <h1 className="font-qb text-qb-h5 font-semibold tracking-normal text-qb-ink-body">{title}</h1>
          {description ? <p className="mt-2 text-qb-caption font-medium text-qb-ink-subtle">{description}</p> : null}
        </div>
        {actions ? <div className="flex shrink-0 flex-wrap gap-2">{actions}</div> : null}
      </div>
    </header>
  );
}

/**
 * White bordered button of the panel headers ("Payment Method", "Withdraw Funds").
 * Phones show the icon only (613:31879), so put the text in a `HeaderButtonLabel`.
 */
export const headerButton =
  'size-11 gap-2 rounded-qb-md border border-qb-line bg-qb-surface px-0 text-qb-body font-normal text-qb-ink shadow-qb-card hover:bg-qb-hover qb-tablet:w-auto qb-tablet:px-4 [&_svg]:size-5';

/** Text of a `headerButton`: the button's accessible name everywhere, visible from the tablet up. */
export function HeaderButtonLabel({ children }: { children: ReactNode }) {
  return <span className="sr-only qb-tablet:not-sr-only">{children}</span>;
}
