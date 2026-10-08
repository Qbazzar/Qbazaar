import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

const ICON_TONES = {
  brand: 'bg-qb-brand-soft text-qb-brand',
  info: 'bg-qb-info-soft text-qb-info',
  success: 'bg-qb-success-soft text-qb-success',
  danger: 'bg-qb-danger-soft text-qb-danger',
} as const;

export interface StatTileProps {
  label: string;
  value: ReactNode;
  /** Decorative icon in the tinted tile, e.g. `<TrendingUp />`. */
  icon: ReactNode;
  tone?: keyof typeof ICON_TONES;
  /** Short line under the value, e.g. a limit or a link. */
  hint?: ReactNode;
  className?: string;
}

/**
 * Figure tile of the sales-overview and wallet screens (502:21437, 573:33241,
 * 600:30639): tinted icon tile, label, large value.
 */
export function StatTile({ label, value, icon, tone = 'brand', hint, className }: StatTileProps) {
  return (
    <div
      className={cn(
        'flex flex-col rounded-qb-xl border border-qb-line bg-qb-surface px-[17px] pt-[11px] pb-3 font-qb shadow-qb-card',
        'qb-tablet:rounded-qb-2xl qb-tablet:p-[17px] qb-desktop:px-6 qb-desktop:pt-[34px] qb-desktop:pb-6',
        className,
      )}
    >
      <span
        aria-hidden="true"
        className={cn(
          'flex size-[34px] items-center justify-center rounded-qb-md [&_svg]:size-5',
          'qb-tablet:size-9 qb-desktop:size-[47px] qb-desktop:rounded-qb-lg qb-desktop:[&_svg]:size-6',
          ICON_TONES[tone],
        )}
      >
        {icon}
      </span>
      <p className="mt-2.5 text-qb-micro text-qb-ink-subtle qb-desktop:mt-2 qb-desktop:text-qb-body">{label}</p>
      <p className="mt-0.5 text-qb-body-lg font-semibold break-words text-qb-ink-title qb-tablet:text-qb-body qb-desktop:mt-1 qb-desktop:text-qb-h2">
        {value}
      </p>
      {hint ? <div className="mt-1 text-qb-micro text-qb-ink-subtle qb-desktop:text-qb-caption">{hint}</div> : null}
    </div>
  );
}
