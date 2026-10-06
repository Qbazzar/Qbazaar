import type { Locale } from '@/lib/i18n/locale';

const numberLocale = (locale: Locale) => (locale === 'ar' ? 'ar-EG' : 'en-GB');

/**
 * Formats a typed amount ("285000", "19.5") for display without turning it
 * into a float: the whole part stays an exact integer (amounts are capped
 * far below 2^53) and the decimals are kept as typed, padded to two.
 */
export function formatAmount(value: string, locale: Locale): string {
  const [whole, fraction] = value.split('.');
  const lang = numberLocale(locale);
  const wholeText = new Intl.NumberFormat(lang).format(Number(whole));
  if (!fraction) return wholeText;
  const separator = new Intl.NumberFormat(lang).formatToParts(1.5).find((part) => part.type === 'decimal')?.value ?? '.';
  const digits = fraction.padEnd(2, '0');
  const fractionText = new Intl.NumberFormat(lang, { minimumIntegerDigits: digits.length, useGrouping: false }).format(Number(digits));
  return `${wholeText}${separator}${fractionText}`;
}

/** "08.01.2016" in English as on the design; the Arabic locale's own short date in Arabic. */
export function formatDate(iso: string, locale: Locale): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const format = new Intl.DateTimeFormat(numberLocale(locale), { day: '2-digit', month: '2-digit', year: 'numeric' });
  if (locale === 'ar') return format.format(date);
  const parts = format.formatToParts(date);
  const part = (type: Intl.DateTimeFormatPartTypes) => parts.find((item) => item.type === type)?.value ?? '';
  return `${part('day')}.${part('month')}.${part('year')}`;
}
