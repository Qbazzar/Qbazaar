import type { LucideIcon, LucideProps } from 'lucide-react';

import { cn } from '@/lib/utils';

const SIZES = { sm: 16, md: 20, lg: 24 } as const;

export interface IconProps extends Omit<LucideProps, 'size' | 'ref'> {
  icon: LucideIcon;
  size?: keyof typeof SIZES;
  /** Accessible name. Without it the icon is decorative and hidden from assistive tech. */
  label?: string;
  /** Mirror direction-bound icons (arrows, chevrons) in RTL. */
  flipInRtl?: boolean;
}

export function Icon({ icon: Glyph, size = 'md', label, flipInRtl, className, ...props }: IconProps) {
  return (
    <Glyph
      size={SIZES[size]}
      strokeWidth={1.5}
      className={cn('shrink-0', flipInRtl && 'rtl:-scale-x-100', className)}
      {...(label ? { role: 'img', 'aria-label': label } : { 'aria-hidden': true })}
      {...props}
    />
  );
}
