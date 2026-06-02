'use client';

import { setClientLocale, type Locale } from '@/lib/i18n/locale';

/**
 * Seeds the client-side locale (read by the synchronous `t()` / `getLocale()`)
 * from the value the server already resolved from the `NEXT_LOCALE` cookie.
 *
 * Set during render (not in an effect) so the very first client render uses the
 * same locale the server rendered — no text flash, no hydration mismatch. It's
 * an idempotent assignment to a module-level value, safe to repeat.
 */
export function LocaleProvider({
  locale,
  children,
}: {
  locale: Locale;
  children: React.ReactNode;
}) {
  setClientLocale(locale);

  return <>{children}</>;
}
