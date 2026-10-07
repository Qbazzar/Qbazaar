import type { Locale } from './locale';

/**
 * Intl locale for numbers and dates. Both languages write Latin digits (0-9),
 * as the reference design and the Arabic copy do; `nu-latn` pins that on every
 * engine, whose default digits for Arabic differ.
 */
export function intlLocale(locale: Locale): string {
  return locale === 'ar' ? 'ar-QA-u-nu-latn' : 'en-US';
}

/** A grouped number with Latin digits: "1,250" in both languages. */
export function formatNumber(value: number, locale: Locale): string {
  return new Intl.NumberFormat(intlLocale(locale)).format(value);
}
