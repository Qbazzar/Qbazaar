import type { ReactNode } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { cn } from '@/lib/utils';

/** Underlined brand link inside auth copy ("Signup", "Login", "Edit"): `a.qb-link`. */
export const authLinkClass = cn(
  'rounded-qb-xs font-medium text-qb-brand underline hover:text-qb-brand-hover',
  focusRing,
);

/** Geometry of `.qb-btn` in auth.css: 56 px high, r12, 600 16 px. */
export const authButtonClass = 'h-14 rounded-qb-lg text-qb-body font-semibold';

/** The orange `.qb-btn`, a shade darker on hover. */
export const authSubmitClass = cn(authButtonClass, 'hover:bg-qb-auth-btn-hover');

/** `.qb-field input:focus` only turns the border orange; the design system's ring stays off. */
export const authInputClass = 'focus-visible:ring-0 aria-invalid:focus-visible:ring-0';

/** "You didn't have an account? Signup" line at the bottom of an auth card (`.qb-foot`). */
export function AuthFooter({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <p className={cn('mt-[22px] text-center text-qb-body-sm text-qb-auth-foot', className)}>
      {children}
    </p>
  );
}
