/**
 * Tiny relative-time formatter for messaging surfaces.
 *
 * Intl.RelativeTimeFormat handles the Arabic/English pluralisation for us;
 * we just pick the right unit per delta. For deltas older than a week we
 * fall back to a short date so timestamps stay readable.
 */
import { getLocale } from '@/lib/i18n/locale';

const MINUTE = 60_000;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;
const WEEK = 7 * DAY;

export function formatRelativeTime(iso: string | null): string {
  if (!iso) return '';
  const ts = new Date(iso).getTime();
  if (Number.isNaN(ts)) return '';

  const locale = getLocale();
  const lang = locale === 'ar' ? 'ar-EG' : 'en-US';
  const now = Date.now();
  const diff = now - ts;

  const rtf = new Intl.RelativeTimeFormat(lang, { numeric: 'auto' });

  if (diff < MINUTE) return rtf.format(0, 'minute');
  if (diff < HOUR) return rtf.format(-Math.round(diff / MINUTE), 'minute');
  if (diff < DAY) return rtf.format(-Math.round(diff / HOUR), 'hour');
  if (diff < WEEK) return rtf.format(-Math.round(diff / DAY), 'day');

  return new Intl.DateTimeFormat(lang, {
    day: 'numeric',
    month: 'short',
  }).format(new Date(ts));
}

export function formatClockTime(iso: string): string {
  const locale = getLocale();
  const lang = locale === 'ar' ? 'ar-EG' : 'en-US';
  return new Intl.DateTimeFormat(lang, {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(iso));
}

export function formatDaySeparator(iso: string): string {
  const locale = getLocale();
  const lang = locale === 'ar' ? 'ar-EG' : 'en-US';
  const ts = new Date(iso);
  const today = new Date();
  const yesterday = new Date();
  yesterday.setDate(today.getDate() - 1);

  const sameDay = (a: Date, b: Date) =>
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate();

  if (sameDay(ts, today)) return locale === 'ar' ? 'اليوم' : 'Today';
  if (sameDay(ts, yesterday)) return locale === 'ar' ? 'أمس' : 'Yesterday';

  return new Intl.DateTimeFormat(lang, {
    day: 'numeric',
    month: 'long',
  }).format(ts);
}

/** Stable bucket key (YYYY-MM-DD) used to group messages by day. */
export function dayBucketKey(iso: string): string {
  const d = new Date(iso);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}
