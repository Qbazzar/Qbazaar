import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

export interface AccountEmptyStateProps {
  /** Icon in the round orange tile. */
  icon: ReactNode;
  title: string;
  description: string;
  /** "Browse Ads" and similar, under the copy. */
  action?: ReactNode;
  className?: string;
}

/**
 * Empty state of the notifications and saved-search screens of the app
 * template: a white card with a round soft-orange icon tile, a semibold
 * title and one muted sentence.
 */
export function AccountEmptyState({ icon, title, description, action, className }: AccountEmptyStateProps) {
  return (
    <div
      className={cn(
        'flex flex-col items-center gap-4 rounded-qb-2xl border border-qb-line bg-qb-surface px-6 py-[70px] text-center font-qb',
        className,
      )}
    >
      <span
        aria-hidden="true"
        className="flex size-24 items-center justify-center rounded-full bg-qb-brand-soft text-qb-brand [&_svg]:size-10"
      >
        {icon}
      </span>
      <h2 className="text-qb-h3 font-semibold tracking-normal text-qb-ink">{title}</h2>
      <p className="max-w-[420px] text-qb-body leading-[1.6] text-qb-ink-subtle">{description}</p>
      {action ? <div className="mt-2">{action}</div> : null}
    </div>
  );
}
