import type { ButtonHTMLAttributes } from 'react';
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

import { focusRing } from './focus-ring';

export const buttonVariants = cva(
  [
    'inline-flex shrink-0 items-center justify-center gap-2 font-qb font-semibold whitespace-nowrap',
    'qb-motion select-none cursor-pointer',
    'disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50',
    '[&_svg]:pointer-events-none [&_svg]:shrink-0',
    focusRing,
  ],
  {
    variants: {
      // The reference's .qb-btn keeps its colours under the pointer and dims by 4 % (styles.css:243);
      // only the orange buttons swap to the darker orange (polish.css:24-28). Nothing changes on
      // :active, so a pressed button keeps its hover look.
      variant: {
        primary: 'bg-qb-brand text-qb-on-brand hover:bg-qb-brand-hover',
        secondary: 'border border-qb-brand bg-qb-surface text-qb-brand hover:brightness-[.96]',
        outline: 'border border-qb-line bg-qb-surface text-qb-ink-title hover:brightness-[.96]',
        soft: 'bg-qb-brand-soft text-qb-brand-on-soft hover:brightness-[.96]',
        muted: 'bg-qb-fill font-normal text-qb-ink-muted hover:brightness-[.96]',
        ghost: 'font-medium text-qb-ink-muted hover:bg-qb-fill',
        danger: 'border border-qb-line bg-qb-surface text-qb-danger hover:brightness-[.96]',
      },
      // Line height 1.15 as every .qb-btn of the reference, so a padding-sized
      // button (h-auto with py) keeps the reference height. A className that
      // changes the text size replaces it and has to restate it.
      size: {
        sm:'h-10 rounded-qb-md px-[18px] text-qb-caption leading-[1.15] [&_svg]:size-4',
        md: 'h-12 rounded-qb-md px-6 text-qb-body leading-[1.15] [&_svg]:size-5',
        lg: 'h-14 rounded-qb-lg px-8 text-qb-body leading-[1.15] [&_svg]:size-5',
        icon: 'size-10 rounded-qb-md [&_svg]:size-5',
      },
      fullWidth: { true: 'w-full' },
    },
    defaultVariants: { variant: 'primary', size: 'md' },
  },
);

export type ButtonVariantProps = VariantProps<typeof buttonVariants>;

export interface ButtonProps
  extends ButtonHTMLAttributes<HTMLButtonElement>,
    ButtonVariantProps {}

/**
 * Design-system button. For a link that looks like a button, put
 * `buttonVariants({...})` on the `<Link>` instead of nesting a button in it.
 * Icon-only buttons need an `aria-label`.
 */
export function Button({ className, variant, size, fullWidth, type = 'button', ...props }: ButtonProps) {
  return (
    <button
      type={type}
      className={cn(buttonVariants({ variant, size, fullWidth }), className)}
      {...props}
    />
  );
}
