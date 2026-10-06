import type { PriceType } from '@/lib/api/types';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

const numberLocale = () => (getLocale() === 'ar' ? 'ar-EG' : 'en-US');

/** "+974******780": the country code and the last three digits, as on 394:9270. */
export function maskPhone(phone: string | null | undefined): string {
  if (!phone) return '';
  const visibleEnd = 3;
  const prefix = phone.startsWith('+') ? phone.slice(0, 4) : '';
  const hidden = phone.length - prefix.length - visibleEnd;
  if (hidden <= 0) return phone;
  return `${prefix}${'*'.repeat(hidden)}${phone.slice(-visibleEnd)}`;
}

/** "June 01, 2026" style date used in the My Ads meta line. */
export function formatLongDate(iso: string | null | undefined): string {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  return new Intl.DateTimeFormat(numberLocale(), { month: 'long', day: '2-digit', year: 'numeric' }).format(date);
}

/** Grouped integer in the active locale ("1,525"). */
export function formatCount(value: number): string {
  return new Intl.NumberFormat(numberLocale()).format(value);
}

/** Readable label from a slug ("health-and-beauty" -> "health and beauty"). */
export function labelFromSlug(slug: string | null | undefined): string {
  return slug ? slug.replace(/-/g, ' ') : '';
}

/** Price label of an ad photo badge or row: "QAR 287,000", "Free" or "Contact for price". */
export function formatAdPrice(price: number | null, priceType: PriceType): string {
  if (priceType === 'free') return t('ads.price.free');
  if (priceType === 'contact' || price == null) return t('ads.price.contact');
  const amount = new Intl.NumberFormat(numberLocale(), { maximumFractionDigits: 0 }).format(price);
  return t('account.price.amount', { amount });
}
