import type { ReactNode } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { cn } from '@/lib/utils';

/** Underlined brand link inside auth copy ("Signup", "Login", "Resend"). */
export const authLinkClass = cn(
  'rounded-qb-xs font-semibold text-qb-brand underline underline-offset-2 hover:text-qb-brand-hover',
  focusRing,
);

/** Submit button height per breakpoint: 52, 60 and 48 px in 779:40299, 779:39974 and 736:65208. */
export const authSubmitClass =
  'h-[52px] qb-tablet:h-[60px] qb-tablet:rounded-qb-lg qb-tablet:text-qb-body-lg qb-desktop:h-12 qb-desktop:rounded-qb-md qb-desktop:text-qb-body';

/** "You didn't have an account? Signup" line at the bottom of an auth card. */
export function AuthFooter({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <p className={cn('mt-2 text-center text-qb-caption text-qb-ink-subtle qb-tablet:text-qb-body', className)}>
      {children}
    </p>
  );
}
