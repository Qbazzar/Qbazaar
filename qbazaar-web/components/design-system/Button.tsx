import type { ButtonHTMLAttributes } from 'react';
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

import { focusRing } from './focus-ring';

export const buttonVariants = cva(
  [
    'inline-flex shrink-0 items-center justify-center gap-2 font-qb font-semibold whitespace-nowrap',
    'transition-colors duration-150 select-none cursor-pointer',
    'disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50',
    '[&_svg]:pointer-events-none [&_svg]:shrink-0',
    focusRing,
  ],
  {
    variants: {
      variant: {
        primary: 'bg-qb-brand text-qb-on-brand hover:bg-qb-brand-hover active:bg-qb-brand-active',
        secondary: 'border border-qb-brand bg-qb-surface text-qb-brand hover:bg-qb-brand-soft hover:text-qb-brand-on-soft',
        outline: 'border border-qb-line bg-qb-surface text-qb-ink-title hover:bg-qb-hover',
        soft: 'bg-qb-brand-soft text-qb-brand-on-soft hover:bg-qb-brand hover:text-qb-on-brand',
        muted: 'bg-qb-fill font-normal text-qb-ink-muted hover:bg-qb-line hover:text-qb-ink-secondary',
        ghost: 'font-medium text-qb-ink-muted hover:bg-qb-fill',
        danger: 'border border-qb-line bg-qb-surface text-qb-danger hover:bg-qb-danger-soft',
      },
      size: {
        sm: 'h-10 rounded-qb-md px-[18px] text-qb-caption [&_svg]:size-4',
        md: 'h-12 rounded-qb-md px-6 text-qb-body [&_svg]:size-5',
        lg: 'h-14 rounded-qb-lg px-8 text-qb-body [&_svg]:size-5',
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
