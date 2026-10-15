import type { ReactNode, SVGProps } from 'react';

import type { Locale } from '@/lib/i18n/locale';
import { cn } from '@/lib/utils';

/**
 * The flags of the auth language list as inline SVG. The reference writes them as emoji, which
 * Windows browsers draw as the letters "GB" and "QA"; the drawing looks the same everywhere.
 */
const FLAGS: Record<Locale, { title: string; shapes: ReactNode }> = {
  en: {
    title: 'GB',
    shapes: (
      <>
        <rect width="60" height="40" fill="#012169" />
        <path d="M0 0 60 40M60 0 0 40" stroke="#fff" strokeWidth="8" />
        <path d="M0 0 60 40M60 0 0 40" stroke="#C8102E" strokeWidth="3" />
        <path d="M30 0V40M0 20H60" stroke="#fff" strokeWidth="13" />
        <path d="M30 0V40M0 20H60" stroke="#C8102E" strokeWidth="8" />
      </>
    ),
  },
  ar: {
    title: 'QA',
    shapes: (
      <>
        <rect width="60" height="40" fill="#8A1538" />
        <path d="M0 0 H17 L26 2.22 L17 4.44 L26 6.67 L17 8.89 L26 11.11 L17 13.33 L26 15.56 L17 17.78 L26 20.00 L17 22.22 L26 24.44 L17 26.67 L26 28.89 L17 31.11 L26 33.33 L17 35.56 L26 37.78 L17 40.00 H0 Z" fill="#fff" />
      </>
    ),
  },
};

/** A 20 × 14 flag, decorative: the language name beside it carries the meaning. */
export function FlagIcon({ locale, className, ...props }: { locale: Locale } & Omit<SVGProps<SVGSVGElement>, 'viewBox'>) {
  return (
    <svg
      viewBox="0 0 60 40"
      preserveAspectRatio="none"
      width="20"
      height="14"
      aria-hidden="true"
      focusable="false"
      className={cn('shrink-0 rounded-[2px]', className)}
      {...props}
    >
      {FLAGS[locale].shapes}
    </svg>
  );
}
