import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

export interface AuthHeadingProps {
  title: ReactNode;
  subtitle?: ReactNode;
  /** `start` for the cards whose copy reads as a paragraph ("Check your email"). */
  align?: 'center' | 'start';
  className?: string;
}

/** Title + muted subtitle at the top of every auth card (`.qb-card h1` + `.qb-sub`). */
export function AuthHeading({ title, subtitle, align = 'center', className }: AuthHeadingProps) {
  return (
    <div className={className}>
      <h1 className="text-center text-qb-h2 leading-[1.3] font-semibold tracking-normal text-qb-ink">{title}</h1>
      {subtitle ? (
        <p
          className={cn(
            'mt-2.5 text-qb-body leading-[1.55] text-qb-auth-muted',
            align === 'center' ? 'text-center' : 'mt-3.5 text-start',
          )}
        >
          {subtitle}
        </p>
      ) : null}
    </div>
  );
}
