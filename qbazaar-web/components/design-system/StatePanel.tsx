import type { LucideIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

import { Card } from './Card';
import { EmptyState, type EmptyStateProps } from './EmptyState';
import { Icon } from './Icon';

export type StatePanelProps = EmptyStateProps;

/**
 * Empty, not-found and error states in the white 24 px panel of the "Search
 * Not Found" and empty wishlist screens (655:55973, 655:55238, 608:26579,
 * 376:7817).
 */
export function StatePanel({ className, ...emptyState }: StatePanelProps) {
  return (
    <Card
      large
      elevated
      padding="none"
      className={cn(
        'flex min-h-[427px] items-center justify-center qb-tablet:min-h-[512px] qb-desktop:min-h-[415px]',
        className,
      )}
    >
      {/* The heading takes the design face itself: the base h1-h6 rule would otherwise win over the inherited one. */}
      <EmptyState {...emptyState} className="[&_:is(h2,h3)]:font-qb" />
    </Card>
  );
}

const ICON_TONES = {
  brand: 'text-qb-brand',
  muted: 'text-qb-ink-muted',
  success: 'text-qb-success',
} as const;

export interface StateIconProps {
  icon: LucideIcon;
  /** Grey for search and failures (655:55973), brand for empty lists (376:7817). */
  tone?: keyof typeof ICON_TONES;
  className?: string;
}

/** The 36 / 40 px glyph inside a state tile. */
export function StateIcon({ icon, tone = 'brand', className }: StateIconProps) {
  return <Icon icon={icon} className={cn('size-9 qb-tablet:size-10', ICON_TONES[tone], className)} />;
}
