import { intlLocale } from '@/lib/i18n/format';
import type { Locale } from '@/lib/i18n/locale';

/** Dates are shown in Qatar time whatever the visitor's clock says. */
const TIME_ZONE = 'Asia/Qatar';

function parse(iso: string | null | undefined): Date | null {
  if (!iso) return null;
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? null : date;
}

/** "08.01.2016": the ad detail's "Posted on" and "Active since" (product.html). */
export function formatDottedDate(iso: string | null | undefined): string {
  const date = parse(iso);
  if (!date) return '';
  const parts = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: TIME_ZONE }).formatToParts(date);
  const part = (type: Intl.DateTimeFormatPartTypes) => parts.find((item) => item.type === type)?.value ?? '';
  return `${part('day')}.${part('month')}.${part('year')}`;
}

/** "08 Jan 2016": the seller card's "Member Since" (seller-individual.html). */
export function formatDayMonthYear(iso: string | null | undefined, locale: Locale): string {
  const date = parse(iso);
  if (!date) return '';
  const tag = locale === 'ar' ? intlLocale(locale) : 'en-GB';
  return new Intl.DateTimeFormat(tag, { day: '2-digit', month: 'short', year: 'numeric', timeZone: TIME_ZONE }).format(date);
}
