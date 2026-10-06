import type { HTMLAttributes } from 'react';
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

export const cardVariants = cva('rounded-qb-xl border border-qb-line bg-qb-surface font-qb', {
  variants: {
    padding: {
      none: '',
      sm: 'p-4',
      md: 'p-6',
    },
    /** Panels that float on the page (filter sidebar, profile header). */
    elevated: { true: 'shadow-qb-card' },
    /** Large page panels (empty states, settings panels) use the 24px radius. */
    large: { true: 'rounded-qb-2xl' },
  },
  defaultVariants: { padding: 'md' },
});

export interface CardProps extends HTMLAttributes<HTMLDivElement>, VariantProps<typeof cardVariants> {}

export function Card({ className, padding, elevated, large, ...props }: CardProps) {
  return <div className={cn(cardVariants({ padding, elevated, large }), className)} {...props} />;
}
