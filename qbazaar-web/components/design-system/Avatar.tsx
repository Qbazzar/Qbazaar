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
  /** Hidden from assistive tech, for an avatar next to the printed name or inside a named link. */
  decorative?: boolean;
  className?: string;
}

/** The Arabic article at the start of a word, so "العقارية" gives "ع", not "ا". */
const ARABIC_ARTICLE = /^ال(?=\p{L})/u;

export function initialsOf(name: string): string {
  const words = name.trim().split(/\s+/).filter(Boolean);
  const letters = words.length > 1 ? [words[0], words[words.length - 1]] : words;
  return letters.map((word) => Array.from(word.replace(ARABIC_ARTICLE, ''))[0]).join('');
}

export function Avatar({ name, src, size, tone, decorative = false, className }: AvatarProps) {
  const classes = cn(avatarVariants({ size, tone }), className);
  if (src) {
    return <img src={src} alt={decorative ? '' : name} loading="lazy" decoding="async" className={cn(classes, 'object-cover')} />;
  }
  if (decorative) {
    return (
      <span aria-hidden="true" className={classes}>
        {initialsOf(name)}
      </span>
    );
  }
  return (
    <span role="img" aria-label={name} className={classes}>
      <span aria-hidden="true">{initialsOf(name)}</span>
    </span>
  );
}
