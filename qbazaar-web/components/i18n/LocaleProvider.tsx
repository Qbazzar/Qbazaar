'use client';

import { createContext, use } from 'react';

import { registerRenderLocaleReader, setClientLocale, type Locale } from '@/lib/i18n/locale';

// The locale of the render in progress. Only the server reads it: one server
// process renders many requests at once, while a browser tab renders one locale.
const RenderLocaleContext = createContext<Locale | null>(null);

if (typeof window === 'undefined') {
  registerRenderLocaleReader(() => use(RenderLocaleContext));
}

/**
 * Seeds the client-side locale (read by the synchronous `t()` / `getLocale()`)
 * from the value the server already resolved from the `NEXT_LOCALE` cookie.
 *
 * Set during render (not in an effect) so the very first client render uses the
 * same locale the server rendered — no text flash, no hydration mismatch. It's
 * an idempotent assignment to a module-level value, safe to repeat. The context
 * keeps concurrent server renders apart (see lib/i18n/locale).
 */
export function LocaleProvider({
  locale,
  children,
}: {
  locale: Locale;
  children: React.ReactNode;
}) {
  setClientLocale(locale);

  return <RenderLocaleContext value={locale}>{children}</RenderLocaleContext>;
}
