import type { ReactNode } from 'react';

import { Badge } from '@/components/design-system/Badge';
import type { StatusTone } from '@/lib/orders/status';
import { cn } from '@/lib/utils';

export interface StatusPillProps {
  tone: StatusTone;
  children: ReactNode;
  /** The small filled tag of the phone lists (600:30639): 10 px, no outline. */
  compact?: boolean;
  /** The outlined label of the chat cards. */
  outline?: boolean;
  className?: string;
}

/**
 * Status label ("Complete", "Pending") set in the label face: the filled 14 px r8 pill of
 * sales-overview.html's table, or the outlined one of the chat cards.
 */
export function StatusPill({ tone, children, compact = false, outline = false, className }: StatusPillProps) {
  return (
    <Badge
      tone={tone}
      className={cn(
        compact && 'rounded-[4px] px-1.5 py-0.5 font-qb text-qb-tiny font-semibold',
        !compact && outline && 'border border-current px-2.5 py-0.5 font-qb-label text-qb-micro qb-tablet:px-3.5 qb-tablet:text-qb-caption',
        !compact && !outline && 'px-4 py-[5px] font-qb-label text-qb-caption',
        className,
      )}
    >
      {children}
    </Badge>
  );
}
