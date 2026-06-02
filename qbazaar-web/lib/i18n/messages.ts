/**
 * Synchronous i18n lookup used across the app as `t('dot.path')`.
 *
 * Both locale dictionaries are bundled; the active one is chosen per call via
 * `getLocale()` (cookie-driven — see ./locale), so a language switch is just a
 * cookie change + reload, with no change to the ~120 call-sites.
 */
import ar from '@/i18n/ar.json';
import en from '@/i18n/en.json';

import { getLocale, type Locale } from './locale';

type Dict = Record<string, unknown>;

const dictionaries: Record<Locale, Dict> = {
  ar: ar as Dict,
  en: en as Dict,
};

function activeMessages(): Dict {
  return dictionaries[getLocale()] ?? dictionaries.ar;
}

export function t(
  key: string,
  varsOrFallback?: Record<string, string | number> | string,
  maybeFallback?: string,
): string {
  // Support both `t(key)`, `t(key, fallback)`, and `t(key, vars)` / `t(key, vars, fallback)`.
  const vars =
    typeof varsOrFallback === 'object' && varsOrFallback !== null
      ? varsOrFallback
      : undefined;
  const fallback =
    typeof varsOrFallback === 'string' ? varsOrFallback : maybeFallback;

  const parts = key.split('.');
  let cur: unknown = activeMessages();
  for (const part of parts) {
    if (cur && typeof cur === 'object' && part in (cur as Dict)) {
      cur = (cur as Dict)[part];
    } else {
      return fallback ?? key;
    }
  }
  const raw = typeof cur === 'string' ? cur : (fallback ?? key);
  if (!vars) return raw;
  return raw.replace(/\{(\w+)\}/g, (_, name: string) =>
    name in vars ? String(vars[name]) : `{${name}}`,
  );
}

/**
 * Translate a Zod error message that we encoded as an i18n key, e.g.
 * `auth.errors.password_min`. If the path doesn't resolve we return the
 * original message untouched (useful for "real" runtime errors).
 */
export function translateMaybeKey(message: string | undefined): string {
  if (!message) return '';
  if (!message.includes('.')) return message;
  const translated = t(message);
  // If we got the key back unchanged, it wasn't a known key — return raw.
  return translated === message ? message : translated;
}
