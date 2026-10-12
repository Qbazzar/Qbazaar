import { cn } from '@/lib/utils';

import { isGlyphComponent, type GlyphProps, type IconGlyph } from './icons/glyph';

const SIZES = { sm: 16, md: 20, lg: 24 } as const;

/** Lucide icons not yet moved to the registry keep the stroke they had, so nothing changes before the codemod reaches them. */
const LEGACY_LUCIDE_STROKE = 1.5;

export interface IconProps extends Omit<GlyphProps, 'size' | 'ref'> {
  /** A registry glyph (`HeartIcon` from `@/components/design-system/icons`); lucide icons are accepted until phase 2 ends. */
  icon: IconGlyph;
  /** Exact px (the reference size of that usage) or a step of the old scale: sm 16, md 20, lg 24. */
  size?: keyof typeof SIZES | number;
  /** Accessible name. Without it the icon is decorative and hidden from assistive tech. */
  label?: string;
  /** Mirror direction-bound icons (arrows, chevrons) in RTL. */
  flipInRtl?: boolean;
}

/** Reference glyphs draw with their own stroke width; pass `strokeWidth` only where the design uses another variant. */
export function Icon({ icon: Glyph, size = 'md', label, flipInRtl, className, ...props }: IconProps) {
  const px = typeof size === 'number' ? size : SIZES[size];
  return (
    <Glyph
      size={px}
      {...(isGlyphComponent(Glyph) ? null : { strokeWidth: LEGACY_LUCIDE_STROKE })}
      className={cn('shrink-0', flipInRtl && 'rtl:-scale-x-100', className)}
      {...(label ? { role: 'img', 'aria-label': label } : { 'aria-hidden': true })}
      {...props}
    />
  );
}
