import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

export const avatarVariants = cva(
  'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full font-qb font-semibold uppercase select-none',
  {
    variants: {
      size: {
        sm: 'size-10 text-qb-caption',
        md: 'size-14 text-qb-body-lg',
        lg: 'size-[66px] text-qb-h4',
      },
      tone: {
        /** Header account button. */
        neutral: 'bg-qb-fill-strong text-qb-ink-body',
        /** Profile headers and seller cards. */
        brand: 'bg-qb-brand-soft text-qb-brand-on-soft',
      },
    },
    defaultVariants: { size: 'sm', tone: 'neutral' },
  },
);

export interface AvatarProps extends VariantProps<typeof avatarVariants> {
  /** Person or company name: the alt text and the source of the initials. */
  name: string;
  src?: string | null;
  className?: string;
}

export function initialsOf(name: string): string {
  const words = name.trim().split(/\s+/).filter(Boolean);
  const letters = words.length > 1 ? [words[0], words[words.length - 1]] : words;
  return letters.map((word) => Array.from(word)[0]).join('');
}

export function Avatar({ name, src, size, tone, className }: AvatarProps) {
  const classes = cn(avatarVariants({ size, tone }), className);
  if (src) {
    return <img src={src} alt={name} loading="lazy" decoding="async" className={cn(classes, 'object-cover')} />;
  }
  return (
    <span role="img" aria-label={name} className={classes}>
      <span aria-hidden="true">{initialsOf(name)}</span>
    </span>
  );
}
