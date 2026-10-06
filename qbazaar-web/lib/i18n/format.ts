import type { Locale } from './locale';

/** Intl locale for numbers and dates: Arabic pages show Arabic-Indic digits. */
export function intlLocale(locale: Locale): string {
  return locale === 'ar' ? 'ar-EG' : 'en-US';
}

/** A grouped number in the page's digits: "1,250" or "١٬٢٥٠". */
export function formatNumber(value: number, locale: Locale): string {
  return new Intl.NumberFormat(intlLocale(locale)).format(value);
}
