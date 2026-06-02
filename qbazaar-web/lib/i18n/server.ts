import 'server-only';

import { cookies } from 'next/headers';

import {
  DEFAULT_LOCALE,
  LOCALE_COOKIE,
  isLocale,
  primeServerLocale,
  type Locale,
} from './locale';

/**
 * Resolve the request locale from the `NEXT_LOCALE` cookie and prime the
 * request-scoped server holder so every synchronous `getLocale()` / `t()` call
 * downstream in this render sees it. Call once, high in the root layout.
 */
export async function resolveServerLocale(): Promise<Locale> {
  const store = await cookies();
  const value = store.get(LOCALE_COOKIE)?.value;
  const locale: Locale = isLocale(value) ? value : DEFAULT_LOCALE;

  primeServerLocale(locale);

  return locale;
}
