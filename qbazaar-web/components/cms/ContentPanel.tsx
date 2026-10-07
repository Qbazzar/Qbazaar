import type { ReactNode } from 'react';

import { Card } from '@/components/design-system/Card';
import { cn } from '@/lib/utils';

/** White 24 px panel holding a long-form body, as in the empty wishlist frame (376:7817). */
export function ContentPanel({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <Card large elevated padding="none" className={cn('px-5 py-6 qb-tablet:p-8 qb-desktop:p-10', className)}>
      {children}
    </Card>
  );
}
