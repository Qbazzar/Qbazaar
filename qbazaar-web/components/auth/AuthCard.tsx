import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/** White r24 card of the auth screens: 358, 584 and 716 px wide at 390, 744 and 1440. */
export function AuthCard({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={cn(
        'h-fit w-full max-w-[584px] rounded-qb-2xl border border-qb-line bg-qb-surface p-[25px] font-qb text-qb-ink shadow-qb-card',
        'qb-tablet:p-12 qb-desktop:max-w-[716px] qb-desktop:px-11 qb-desktop:py-8',
        className,
      )}
    >
      {children}
    </div>
  );
}
