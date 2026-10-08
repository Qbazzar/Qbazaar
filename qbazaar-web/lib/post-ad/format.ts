import { intlLocale } from '@/lib/i18n/format';
import type { Locale } from '@/lib/i18n/locale';

/**
 * Formats a typed amount ("285000", "19.5") for display without turning it
 * into a float: the whole part stays an exact integer (amounts are capped
 * far below 2^53) and the decimals are kept as typed, padded to two.
 */
export function formatAmount(value: string, locale: Locale): string {
  const [whole, fraction] = value.split('.');
  const format = new Intl.NumberFormat(intlLocale(locale));
  const wholeText = format.format(Number(whole));
  if (!fraction) return wholeText;
  const separator = format.formatToParts(1.5).find((part) => part.type === 'decimal')?.value ?? '.';
  return `${wholeText}${separator}${fraction.padEnd(2, '0')}`;
}

/** "08.01.2016", the date as the design writes it, in both languages. */
export function formatDate(iso: string, locale: Locale): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const parts = new Intl.DateTimeFormat(intlLocale(locale), { day: '2-digit', month: '2-digit', year: 'numeric' }).formatToParts(date);
  const part = (type: Intl.DateTimeFormatPartTypes) => parts.find((item) => item.type === type)?.value ?? '';
  return `${part('day')}.${part('month')}.${part('year')}`;
}
