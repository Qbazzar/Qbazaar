import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

export interface CheckoutPanelProps {
  title: string;
  titleId: string;
  /** Control on the end side of the heading, e.g. a "Change" link. */
  action?: ReactNode;
  children: ReactNode;
  className?: string;
}

/**
 * White r24 card with a divided heading, as the checkout sections (682:32513).
 * Radio groups inside label themselves with `aria-labelledby={titleId}`.
 */
export function CheckoutPanel({ title, titleId, action, children, className }: CheckoutPanelProps) {
  return (
    <section
      aria-labelledby={titleId}
      className={cn(
        'rounded-qb-2xl border border-qb-line bg-qb-surface p-4 font-qb shadow-qb-card qb-tablet:p-6 qb-desktop:p-8',
        className,
      )}
    >
      <div className="flex items-center justify-between gap-4 border-b border-qb-line pb-4 qb-desktop:pb-6">
        <h2
          id={titleId}
          className="text-qb-body-lg font-medium tracking-normal text-qb-ink-title qb-tablet:text-qb-h5 qb-desktop:text-qb-h4"
        >
          {title}
        </h2>
        {action}
      </div>
      <div className="mt-4 qb-desktop:mt-6">{children}</div>
    </section>
  );
}
