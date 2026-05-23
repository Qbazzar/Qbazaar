/**
 * Minimal i18n shim for Wave 1.
 *
 * Wave 1 ships a single Arabic locale, so we import the JSON statically and
 * expose a `t(key)` helper that walks dot-paths. Wave 2 will replace this
 * with `next-intl` once the `[locale]` segment lands.
 *
 * The same JSON files are used here and will be picked up by `next-intl`
 * later without changes.
 */
import ar from '@/i18n/ar.json';

type Dict = Record<string, unknown>;
const messages: Dict = ar as Dict;

export function t(key: string, fallback?: string): string {
  const parts = key.split('.');
  let cur: unknown = messages;
  for (const part of parts) {
    if (cur && typeof cur === 'object' && part in (cur as Dict)) {
      cur = (cur as Dict)[part];
    } else {
      return fallback ?? key;
    }
  }
  return typeof cur === 'string' ? cur : (fallback ?? key);
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
