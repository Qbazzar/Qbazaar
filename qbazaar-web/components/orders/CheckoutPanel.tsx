import type { ReactNode } from 'react';

import { cardVariants } from '@/components/design-system/Card';
import { cn } from '@/lib/utils';

/** The white r24 panels of the order pages: the design-system card, large and elevated, without padding. */
export const panelClass = cn(cardVariants({ large: true, elevated: true, padding: 'none' }));

/** Padding of the form panels: 16 px on phones, 24 on tablets, 32 on desktop. */
export const panelPadding = 'p-4 qb-tablet:p-6 qb-desktop:p-8';

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
    <section aria-labelledby={titleId} className={cn(panelClass, panelPadding, className)}>
      <div className="flex items-center justify-between gap-4 border-b border-qb-line pb-4 qb-desktop:pb-6">
        <h2
          id={titleId}
          className="font-qb text-qb-body-lg font-medium tracking-normal text-qb-ink-title qb-tablet:text-qb-h5 qb-desktop:text-qb-h4"
        >
          {title}
        </h2>
        {action}
      </div>
      <div className="mt-4 qb-desktop:mt-6">{children}</div>
    </section>
  );
}
