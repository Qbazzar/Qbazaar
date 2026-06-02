'use client';

import {
  LOCALE_COOKIE,
  getLocale,
  setClientLocale,
  type Locale,
} from '@/lib/i18n/locale';

/**
 * One-click Arabic ⇄ English toggle. Persists the choice in the `NEXT_LOCALE`
 * cookie and reloads so the server re-renders with the new locale (html
 * lang/dir + any server-rendered text).
 */
export function LocaleSwitcher({ className }: { className?: string }) {
  const next: Locale = getLocale() === 'ar' ? 'en' : 'ar';

  function switchLocale() {
    document.cookie = `${LOCALE_COOKIE}=${next}; path=/; max-age=31536000; samesite=lax`;
    setClientLocale(next);
    window.location.reload();
  }

  return (
    <button
      type="button"
      onClick={switchLocale}
      aria-label={next === 'en' ? 'Switch to English' : 'التبديل إلى العربية'}
      title={next === 'en' ? 'English' : 'العربية'}
      className={
        className ??
        'inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2 text-sm font-bold text-muted-foreground transition-colors hover:bg-accent hover:text-foreground'
      }
    >
      {next === 'en' ? 'EN' : 'ع'}
    </button>
  );
}
