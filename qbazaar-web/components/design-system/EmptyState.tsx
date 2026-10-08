import type { ReactNode, Ref } from 'react';

import { cn } from '@/lib/utils';

export interface EmptyStateProps {
  /** Decorative icon shown in the tile, e.g. `<Icon icon={Heart} size="lg" />`. */
  icon: ReactNode;
  title: string;
  description?: ReactNode;
  /** Call to action, usually a `Button` or a link styled with `buttonVariants`. */
  action?: ReactNode;
  /** `h1` for a state that is the whole page ("Ad not found"). */
  headingLevel?: 'h1' | 'h2' | 'h3';
  /** Lets the page move focus to the heading when the state replaces other content. */
  headingRef?: Ref<HTMLHeadingElement>;
  className?: string;
}

/** "Found something interesting?" block of the empty wishlist, saved searches and messages screens. */
export function EmptyState({ icon, title, description, action, headingLevel: Heading = 'h2', headingRef, className }: EmptyStateProps) {
  return (
    <div className={cn('flex flex-col items-center px-4 py-12 text-center font-qb', className)}>
      <div
        aria-hidden="true"
        className="mb-6 flex size-[92px] items-center justify-center rounded-qb-2xl border border-qb-line bg-qb-surface text-qb-brand shadow-qb-brand"
      >
        {icon}
      </div>
      <Heading
        ref={headingRef}
        tabIndex={headingRef ? -1 : undefined}
        className="font-qb text-qb-body-lg font-medium tracking-normal text-qb-ink-muted outline-none qb-tablet:text-qb-h4"
      >
        {title}
      </Heading>
      {description ? (
        <p className="mt-2 max-w-[806px] text-qb-body text-qb-ink-muted qb-tablet:text-qb-h5">{description}</p>
      ) : null}
      {action ? <div className="mt-6">{action}</div> : null}
    </div>
  );
}
