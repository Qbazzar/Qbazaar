import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

export interface AuthHeadingProps {
  title: ReactNode;
  subtitle?: ReactNode;
  /** Icon shown in the white tile above the title (verify screens, 736:66642). */
  icon?: ReactNode;
  className?: string;
}

/** Centred title + muted subtitle at the top of every auth card. */
export function AuthHeading({ title, subtitle, icon, className }: AuthHeadingProps) {
  return (
    <div className={cn('text-center', className)}>
      {icon ? (
        <div
          aria-hidden="true"
          className="mx-auto mb-4 flex size-[72px] items-center justify-center rounded-qb-2xl border border-qb-line bg-qb-surface text-qb-brand shadow-qb-brand qb-tablet:size-[92px] [&_svg]:size-7"
        >
          {icon}
        </div>
      ) : null}
      <h1 className="text-qb-h3 leading-tight font-semibold tracking-normal text-qb-ink qb-tablet:text-[32px] qb-desktop:text-qb-h2">
        {title}
      </h1>
      {subtitle ? (
        <p className="mx-auto mt-3 max-w-[584px] text-qb-caption leading-relaxed text-qb-ink-subtle qb-tablet:mt-4 qb-tablet:text-qb-body">
          {subtitle}
        </p>
      ) : null}
    </div>
  );
}
