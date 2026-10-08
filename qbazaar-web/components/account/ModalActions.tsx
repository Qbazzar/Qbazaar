import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * The two equal-width buttons under a dialog or a dialog form: the action,
 * then cancel (401:10866, 411:9755).
 */
export function ModalActions({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={cn('flex gap-3 *:min-w-0 *:flex-1 *:basis-0 qb-tablet:gap-5', className)}>{children}</div>;
}
