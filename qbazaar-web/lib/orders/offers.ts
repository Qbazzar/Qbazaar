import type { DealAd } from '@/lib/api/commerce-types';

export interface SuggestedOffer {
  /** API decimal string, e.g. "1470.00". */
  amount: string;
  /** How far below the asking price, in percent. */
  percent: number;
}

const DISCOUNTS = [5, 10, 15, 20] as const;

/** Rounding step that keeps suggestions readable for the price range. */
function stepFor(price: number): number {
  if (price >= 100_000) return 1000;
  if (price >= 10_000) return 100;
  if (price >= 1000) return 10;
  return 5;
}

/**
 * Offers a few percent under the asking price, rounded down to a readable
 * step. Listed prices are whole riyals, so this is integer arithmetic only.
 */
export function suggestedOffers(askingPrice: number | null | undefined): SuggestedOffer[] {
  if (askingPrice == null || !Number.isFinite(askingPrice)) return [];
  const whole = Math.floor(askingPrice);
  if (whole < 20) return [];

  const step = stepFor(whole);
  const seen = new Set<number>();
  const out: SuggestedOffer[] = [];
  for (const percent of DISCOUNTS) {
    const rounded = Math.floor(Math.floor((whole * (100 - percent)) / 100) / step) * step;
    if (rounded <= 0 || rounded >= whole || seen.has(rounded)) continue;
    seen.add(rounded);
    out.push({ amount: `${rounded}.00`, percent });
  }
  return out;
}

export type DealBlocker = 'own_ad' | 'not_buyable' | 'not_offerable' | 'reserved';

/** Why the viewer cannot send a Buy Now request for this ad, or null when they can. */
export function buyNowBlocker(ad: DealAd, viewerId: string | undefined): DealBlocker | null {
  if (viewerId && viewerId === ad.user_id) return 'own_ad';
  if (ad.status !== 'active' || ad.price_type !== 'fixed' || ad.price == null || ad.ad_type === 'wanted') {
    return 'not_buyable';
  }
  if (ad.is_reserved) return 'reserved';
  return null;
}

/** Why the viewer cannot make an offer on this ad, or null when they can. */
export function offerBlocker(ad: DealAd, viewerId: string | undefined): DealBlocker | null {
  if (viewerId && viewerId === ad.user_id) return 'own_ad';
  if (ad.status !== 'active') return 'not_offerable';
  if (ad.is_reserved) return 'reserved';
  return null;
}
