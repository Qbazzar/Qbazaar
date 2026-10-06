import type { HTMLAttributes } from 'react';
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

export const badgeVariants = cva(
  'inline-flex items-center gap-1 rounded-qb-sm font-qb font-medium whitespace-nowrap [&_svg]:size-3.5 [&_svg]:shrink-0',
  {
    variants: {
      tone: {
        /** Price and "Top Ad" labels on listing images. */
        solid: 'bg-qb-brand text-white',
        brand: 'bg-qb-brand-soft text-qb-brand',
        success: 'bg-qb-success-soft text-qb-success',
        danger: 'bg-qb-danger-soft text-qb-danger',
        info: 'bg-qb-info-soft text-qb-info',
        neutral: 'bg-qb-fill text-qb-ink-secondary',
      },
      size: {
        sm: 'px-2.5 py-1 text-qb-micro',
        md: 'px-3.5 py-[5px] text-qb-label',
      },
    },
    defaultVariants: { tone: 'brand', size: 'md' },
  },
);

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement>, VariantProps<typeof badgeVariants> {}

/** Status and label pill (Draft / Publish / Reserved, "Private Seller", price tag). */
export function Badge({ className, tone, size, ...props }: BadgeProps) {
  return <span className={cn(badgeVariants({ tone, size }), className)} {...props} />;
}

export const chipVariants = cva(
  'inline-flex items-center gap-1 rounded-qb-xs bg-qb-fill font-qb text-qb-ink-faint whitespace-nowrap',
  {
    variants: {
      size: {
        sm: 'px-2.5 py-1 text-qb-micro',
        md: 'px-3 py-[5px] text-qb-label',
      },
    },
    defaultVariants: { size: 'md' },
  },
);

export interface ChipProps extends HTMLAttributes<HTMLSpanElement>, VariantProps<typeof chipVariants> {}

/** Spec chip on listing cards ("2019", "68,000 km", "Diesel"). */
export function Chip({ className, size, ...props }: ChipProps) {
  return <span className={cn(chipVariants({ size }), className)} {...props} />;
}
