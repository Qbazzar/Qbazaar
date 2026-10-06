import type { ReactNode } from 'react';
import Link from 'next/link';

import { cn } from '@/lib/utils';

import { focusRing } from './focus-ring';

export interface LinkTileProps {
  href: string;
  /** Decorative glyph shown in the soft brand square. */
  icon: ReactNode;
  title: string;
  /** Count line ("1,2M Ads") or a short sentence under the title. */
  description?: ReactNode;
  className?: string;
}

/**
 * Category tile of the all-categories screen (185:6576, 536:32620, 621:27193):
 * icon square, title and a muted line, lifting its shadow on hover.
 */
export function LinkTile({ href, icon, title, description, className }: LinkTileProps) {
  return (
    <Link
      href={href}
      className={cn(
        'flex h-full min-h-[120px] flex-col rounded-qb-xl bg-qb-surface px-[17px] pt-[18px] pb-4 font-qb shadow-qb-card transition-shadow duration-200 hover:shadow-qb-hover motion-reduce:transition-none',
        'qb-tablet:min-h-[148px] qb-tablet:rounded-qb-2xl qb-tablet:pt-5 qb-desktop:pt-[17px]',
        focusRing,
        className,
      )}
    >
      <span
        aria-hidden="true"
        className="flex size-9 shrink-0 items-center justify-center rounded-qb-md bg-qb-brand-soft text-qb-brand qb-tablet:size-[43px] [&_svg]:size-[21px] qb-tablet:[&_svg]:size-6"
      >
        {icon}
      </span>
      {/* The spaces keep the link's accessible name from running the title and the line together. */}
      {' '}
      <span className="mt-2 text-qb-caption font-medium break-words text-qb-ink-body qb-tablet:mt-4 qb-tablet:text-qb-body qb-tablet:text-qb-ink qb-desktop:text-qb-h5">
        {title}
      </span>
      {' '}
      {description ? (
        <span className="mt-1 text-qb-micro text-qb-ink-subtle qb-tablet:mt-1.5 qb-tablet:text-qb-caption">{description}</span>
      ) : null}
    </Link>
  );
}
