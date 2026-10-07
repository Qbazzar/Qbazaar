import { formatAdPrice as formatListingPrice } from '@/lib/ads/format';
import { formatNumber, intlLocale } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { AdSummary } from '@/lib/api/types';

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
  return new Intl.DateTimeFormat(intlLocale(getLocale()), { month: 'long', day: '2-digit', year: 'numeric' }).format(date);
}

/** Grouped count in the active language ("1,525"). */
export function formatCount(value: number): string {
  return formatNumber(value, getLocale());
}

/** Readable label from a slug ("health-and-beauty" -> "Health And Beauty"). */
export function labelFromSlug(slug: string | null | undefined): string {
  if (!slug) return '';
  return slug
    .split('-')
    .filter(Boolean)
    .map((word) => word[0].toUpperCase() + word.slice(1))
    .join(' ');
}

/**
 * Price of an account row or the chat header: the listing cards' wording
 * ("QAR 287,000", "Free", "Contact for price"), plus "Negotiable" when the
 * seller takes offers.
 */
export function formatAdPrice(ad: Pick<AdSummary, 'price' | 'price_type'>): string {
  const price = formatListingPrice(ad, getLocale());
  return ad.price_type === 'negotiable' && ad.price != null ? t('account.price.negotiable', { price }) : price;
}
