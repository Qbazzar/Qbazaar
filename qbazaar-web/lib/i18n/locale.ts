/**
 * Locale resolution for the cookie-based i18n switch.
 *
 * The app ships a single hand-rolled `t()` (see ./messages) used in ~120 files,
 * so rather than migrate every call-site to next-intl hooks we keep `getLocale()`
 * synchronous and feed it from two request-safe sources:
 *
 *  - **Server**: a per-render holder created with React's `cache()`. Each RSC
 *    render pass gets its own object, so two concurrent requests with different
 *    `NEXT_LOCALE` cookies never see each other's locale. The root layout primes
 *    it (see ./server `resolveServerLocale`) before any child renders.
 *  - **Client**: a single module-level value set once by <LocaleProvider> from
 *    the locale the server already resolved — so the first client render matches
 *    the server HTML and there's no hydration flash.
 *  - **Client component, server-rendered**: the provider's context. One server
 *    process renders many requests and a streamed render resumes after other
 *    requests' providers ran, so the module-level value may belong to another
 *    request; the context always names the locale of the render in progress.
 */
import { cache } from 'react';

import type { LocalizedString } from '@/lib/api/types';

export type Locale = 'ar' | 'en';

export const LOCALES: readonly Locale[] = ['ar', 'en'];
export const DEFAULT_LOCALE: Locale = 'ar';
export const LOCALE_COOKIE = 'NEXT_LOCALE';

export function isLocale(value: unknown): value is Locale {
  return value === 'ar' || value === 'en';
}

export function dirFor(locale: Locale): 'rtl' | 'ltr' {
  return locale === 'ar' ? 'rtl' : 'ltr';
}

/* ── Server: request-scoped holder ──────────────────────────────────────── */

// Lazily created so `cache()` is only ever invoked on the server (getLocale
// below guards the call behind `typeof window === 'undefined'`).
let serverHolderFactory: (() => { current: Locale }) | null = null;

function serverHolder(): { current: Locale } {
  serverHolderFactory ??= cache((): { current: Locale } => ({
    current: DEFAULT_LOCALE,
  }));
  return serverHolderFactory();
}

export function primeServerLocale(locale: Locale): void {
  serverHolder().current = locale;
}

/* ── Client: single module-level value ──────────────────────────────────── */

let clientLocale: Locale = DEFAULT_LOCALE;
// True once <LocaleProvider> has run — which only happens in the *client*
// module graph (the provider is a client component). It distinguishes a client
// component being server-rendered (use clientLocale, seeded by the provider in
// this same render) from a true server component (use the request-scoped
// holder primed by the root layout).
let clientGraph = false;

export function setClientLocale(locale: Locale): void {
  clientLocale = locale;
  clientGraph = true;
}

/* ── Client component, server-rendered: the render's own locale ──────────── */

let renderLocaleReader: (() => Locale | null) | null = null;

/** Registered by <LocaleProvider> on the server: reads its context in the render in progress. */
export function registerRenderLocaleReader(reader: () => Locale | null): void {
  renderLocaleReader = reader;
}

function renderLocale(): Locale | null {
  // Development skips it: React warns when context is read inside useMemo or a
  // state initialiser, and one developer's requests do not race each other.
  if (!renderLocaleReader || process.env.NODE_ENV === 'development') return null;
  try {
    return renderLocaleReader();
  } catch {
    // Called outside a render (an event, a timer): no context to read.
    return null;
  }
}

/* ── Unified accessor ───────────────────────────────────────────────────── */

export function getLocale(): Locale {
  if (typeof window !== 'undefined') return clientLocale;
  // A client component being server-rendered: its render's locale, else the
  // value the provider seeded.
  if (clientGraph) return renderLocale() ?? clientLocale;
  // True server component: read the request-scoped holder primed by the layout.
  return serverHolder().current;
}

/**
 * Read the localized side of a `LocalizedString` for the active locale,
 * falling back to the other side or an empty string when missing. Used by
 * every component that renders bilingual reference data.
 */
export function localized(
  value: LocalizedString | null | undefined,
  locale: Locale = getLocale(),
): string {
  if (!value) return '';
  return value[locale] ?? value.ar ?? value.en ?? '';
}
