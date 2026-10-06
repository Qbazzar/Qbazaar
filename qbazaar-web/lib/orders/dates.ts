import { getLocale, type Locale } from '@/lib/i18n/locale';

import { numberLocale } from './money';

function parse(iso: string | null | undefined): Date | null {
  if (!iso) return null;
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? null : date;
}

/** "June 2, 2026" (Arabic: "٢ يونيو ٢٠٢٦"), in Qatar time. */
export function formatDate(iso: string | null | undefined, locale: Locale = getLocale()): string {
  const date = parse(iso);
  if (!date) return '';
  return new Intl.DateTimeFormat(numberLocale(locale), {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    timeZone: 'Asia/Qatar',
  }).format(date);
}

/** "Jun 2, 2026, 3:30 PM", in Qatar time. */
export function formatDateTime(iso: string | null | undefined, locale: Locale = getLocale()): string {
  const date = parse(iso);
  if (!date) return '';
  return new Intl.DateTimeFormat(numberLocale(locale), {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    timeZone: 'Asia/Qatar',
  }).format(date);
}

/** Machine-readable value for `<time dateTime>`. */
export function isoDate(iso: string | null | undefined): string | undefined {
  return parse(iso)?.toISOString();
}
