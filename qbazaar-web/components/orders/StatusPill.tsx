import type { ReactNode } from 'react';

import { Badge } from '@/components/design-system/Badge';
import type { StatusTone } from '@/lib/orders/status';
import { cn } from '@/lib/utils';

export interface StatusPillProps {
  tone: StatusTone;
  children: ReactNode;
  className?: string;
}

/** Outlined status label of the transaction tables and chat cards ("Complete", "Pending"), set in the label face. */
export function StatusPill({ tone, children, className }: StatusPillProps) {
  return (
    <Badge
      tone={tone}
      className={cn(
        'border border-current px-2.5 py-0.5 font-qb-label text-qb-micro qb-tablet:px-3.5 qb-tablet:text-qb-caption',
        className,
      )}
    >
      {children}
    </Badge>
  );
}
