import { formatNumber, intlLocale } from '@/lib/i18n/format';
import { localized, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { Ad, AdSummary, Location, Media } from '@/lib/api/types';

/** Price label of a listing card: "QAR 2,350", "Free" or "Contact for price". */
export function formatAdPrice(ad: Pick<AdSummary, 'price' | 'price_type'>, locale: Locale): string {
  if (ad.price_type === 'free') return t('ads.price.free', 'مجاناً');
  if (ad.price_type === 'contact' || ad.price == null) return t('ads.price.contact', 'بالتواصل');
  return `${t('common.currency', 'ر.ق')} ${formatNumber(ad.price, locale)}`;
}

/** "5 minutes ago" style age of a listing, in minutes, hours or days. */
export function formatAdAge(iso: string | null, locale: Locale): string {
  if (!iso) return '';
  const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60_000);
  const rtf = new Intl.RelativeTimeFormat(intlLocale(locale), { numeric: 'auto' });
  if (minutes < 60) return rtf.format(-minutes, 'minute');
  const hours = Math.round(minutes / 60);
  if (hours < 24) return rtf.format(-hours, 'hour');
  return rtf.format(-Math.round(hours / 24), 'day');
}

/**
 * Name of an ad's place: the localized name when the locations tree has the
 * node, otherwise the slug as words ("al-wakrah" -> "al wakrah").
 */
export function placeLabel(slug: string, locale: Locale, place?: Pick<Location, 'name'> | null): string {
  return place ? localized(place.name, locale) : slug.replace(/-/g, ' ');
}

/** Price an ad is offered at, or null when it has none ("contact for price"). */
export function adOfferPrice(ad: Pick<Ad, 'price' | 'price_type'>): number | null {
  if (ad.price_type === 'free') return 0;
  return ad.price_type === 'contact' ? null : ad.price;
}

/** The stable CDN rendition of a photo: `url` is a signed link that expires, which a shared preview outlives. */
export function publicImageUrl(media: Pick<Media, 'url'> & { sizes?: Partial<Media['sizes']> }): string {
  return media.sizes?.large || media.url;
}
