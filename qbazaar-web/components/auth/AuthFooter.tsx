import type { ReactNode } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { cn } from '@/lib/utils';

/**
 * Underlined brand link inside auth copy ("Signup", "Login", "Edit"): `a.qb-link`
 * keeps its colour under the pointer and has square corners.
 */
export const authLinkClass = cn('font-medium text-qb-brand underline', focusRing);

/**
 * Geometry of `.qb-btn` in auth.css: 56 px high with the label on a 56 px line,
 * no side padding, r12, 600 16 px. The auth buttons are static (no transition).
 */
export const authButtonClass = 'h-14 rounded-qb-lg px-0 text-qb-body leading-[56px] font-semibold transition-none';

/** The orange `.qb-btn`, #ef6f42 under the pointer and while pressed. */
export const authSubmitClass = cn(authButtonClass, 'hover:bg-qb-auth-btn-hover');

/** `.qb-field input:focus` only turns the border orange; the design system's ring stays off. */
export const authFocusClass = 'focus-visible:ring-0 aria-invalid:focus-visible:ring-0';

/** `::placeholder` is #BFBFBF on the auth pages only (auth.css:68); other pages keep the standard #757575. */
export const authPlaceholderClass = 'placeholder:text-qb-placeholder';

export const authInputClass = cn(authPlaceholderClass, authFocusClass);

/** "You didn't have an account? Signup" line at the bottom of an auth card (`.qb-foot`). */
export function AuthFooter({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <p className={cn('mt-[22px] text-center text-qb-body-sm text-qb-auth-foot', className)}>
      {children}
    </p>
  );
}
