import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/** White `.qb-card` of the auth screens: up to 715 px wide, r20 (r16 on phones), no border. */
export function AuthCard({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={cn(
        'h-fit w-full max-w-[715px] rounded-qb-xl bg-qb-surface px-5 py-[30px] font-qb text-qb-ink shadow-qb-auth-card',
        'qb-tablet:rounded-[20px] qb-tablet:px-[45px] qb-tablet:pt-11 qb-tablet:pb-10',
        className,
      )}
    >
      {children}
    </div>
  );
}
