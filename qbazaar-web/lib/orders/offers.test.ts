import { describe, expect, it } from 'vitest';

import type { DealAd } from '@/lib/api/commerce-types';

import { buyNowBlocker, offerBlocker, suggestedOffers } from './offers';

const ad = {
  id: 'ad-1',
  user_id: 'seller-1',
  status: 'active',
  price: 1550,
  price_type: 'fixed',
  ad_type: 'offering',
  is_reserved: false,
} as DealAd;

describe('suggestedOffers', () => {
  it('suggests rounded amounts under the asking price', () => {
    expect(suggestedOffers(1550)).toEqual([
      { amount: '1470.00', percent: 5 },
      { amount: '1390.00', percent: 10 },
      { amount: '1310.00', percent: 15 },
      { amount: '1240.00', percent: 20 },
    ]);
    expect(suggestedOffers(290000)[0]).toEqual({ amount: '275000.00', percent: 5 });
  });

  it('skips duplicates and prices too low to discount', () => {
    expect(suggestedOffers(25).map((offer) => offer.amount)).toEqual(['20.00']);
    expect(suggestedOffers(10)).toEqual([]);
    expect(suggestedOffers(null)).toEqual([]);
  });
});

describe('deal blockers', () => {
  it('lets anyone but the owner buy a live fixed-price ad', () => {
    expect(buyNowBlocker(ad, 'buyer-1')).toBeNull();
    expect(buyNowBlocker(ad, undefined)).toBeNull();
    expect(buyNowBlocker(ad, 'seller-1')).toBe('own_ad');
  });

  it('refuses Buy Now without a fixed price, on wanted or reserved ads', () => {
    expect(buyNowBlocker({ ...ad, price_type: 'negotiable' }, 'buyer-1')).toBe('not_buyable');
    expect(buyNowBlocker({ ...ad, ad_type: 'wanted' }, 'buyer-1')).toBe('not_buyable');
    expect(buyNowBlocker({ ...ad, status: 'sold' }, 'buyer-1')).toBe('not_buyable');
    expect(buyNowBlocker({ ...ad, is_reserved: true }, 'buyer-1')).toBe('reserved');
  });

  it('takes offers on any live ad that is not reserved', () => {
    expect(offerBlocker({ ...ad, price_type: 'negotiable' }, 'buyer-1')).toBeNull();
    expect(offerBlocker({ ...ad, status: 'expired' }, 'buyer-1')).toBe('not_offerable');
    expect(offerBlocker({ ...ad, is_reserved: true }, 'buyer-1')).toBe('reserved');
    expect(offerBlocker(ad, 'seller-1')).toBe('own_ad');
  });
});
