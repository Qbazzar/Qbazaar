'use client';

import type { ReactNode } from 'react';

import { getLocale, type Locale } from '@/lib/i18n/locale';

import { switchLocale } from './switch-locale';

/** Each language offers itself in its own words, so the label reads right in the target language. */
const TARGETS: Record<Locale, { code: string; name: string; label: string; labelWithCode: string }> = {
  en: { code: 'EN', name: 'English', label: 'Switch to English', labelWithCode: 'EN, Switch to English' },
  ar: { code: 'ع', name: 'العربية', label: 'التبديل إلى العربية', labelWithCode: 'ع، التبديل إلى العربية' },
};

/**
 * One-click Arabic ⇄ English toggle that shows the other language's code.
 * The accessible name starts with that visible code, and both are marked up
 * in the target language.
 */
export function LocaleSwitcher({ className, children }: { className?: string; children?: ReactNode }) {
  const next: Locale = getLocale() === 'ar' ? 'en' : 'ar';
  const target = TARGETS[next];

  return (
    <button
      type="button"
      lang={next}
      onClick={() => switchLocale(next)}
      aria-label={children ? target.label : target.labelWithCode}
      title={target.name}
      className={
        className ??
        'inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2 text-sm font-bold text-muted-foreground transition-colors hover:bg-accent hover:text-foreground'
      }
    >
      {children ?? target.code}
    </button>
  );
}
