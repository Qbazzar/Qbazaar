import { LOCALE_COOKIE, setClientLocale, type Locale } from '@/lib/i18n/locale';

/**
 * Stores the language in the `NEXT_LOCALE` cookie and reloads, so the server
 * renders the page again in it (html lang/dir and the server-rendered text).
 */
export function switchLocale(next: Locale): void {
  document.cookie = `${LOCALE_COOKIE}=${next}; path=/; max-age=31536000; samesite=lax`;
  setClientLocale(next);
  window.location.reload();
}
