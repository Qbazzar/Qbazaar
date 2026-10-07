import { afterEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { formatAdPrice, formatCount, formatLongDate, labelFromSlug, maskPhone } from './format';

afterEach(() => setClientLocale('ar'));

describe('maskPhone', () => {
  it('keeps the country code and the last three digits', () => {
    expect(maskPhone('+97439000004')).toBe('+974*****004');
  });

  it('leaves numbers too short to mask alone', () => {
    expect(maskPhone('+974123')).toBe('+974123');
    expect(maskPhone('')).toBe('');
    expect(maskPhone(null)).toBe('');
  });
});

describe('formatAdPrice', () => {
  it('uses the listing price wording and marks negotiable prices', () => {
    setClientLocale('en');
    expect(formatAdPrice({ price: 287000, price_type: 'fixed' })).toBe('QAR 287,000');
    expect(formatAdPrice({ price: 955, price_type: 'negotiable' })).toBe('QAR 955 · Negotiable');
  });

  it('names free and contact-for-price ads instead of a number', () => {
    setClientLocale('en');
    expect(formatAdPrice({ price: 0, price_type: 'free' })).toBe('Free');
    expect(formatAdPrice({ price: null, price_type: 'fixed' })).toBe('Contact for price');
    expect(formatAdPrice({ price: null, price_type: 'negotiable' })).toBe('Contact for price');
    expect(formatAdPrice({ price: 500, price_type: 'contact' })).toBe('Contact for price');
  });

  it('keeps Latin digits and uses the riyal sign in Arabic', () => {
    setClientLocale('ar');
    expect(formatAdPrice({ price: 1200, price_type: 'fixed' })).toBe('ر.ق 1,200');
    expect(formatAdPrice({ price: 1200, price_type: 'negotiable' })).toBe('ر.ق 1,200 · قابل للتفاوض');
  });
});

describe('formatCount, formatLongDate and labelFromSlug', () => {
  it('groups counts with Latin digits in both languages', () => {
    setClientLocale('en');
    expect(formatCount(1525)).toBe('1,525');
    setClientLocale('ar');
    expect(formatCount(1525)).toBe('1,525');
  });

  it('writes the long date of the My Ads meta line', () => {
    setClientLocale('en');
    expect(formatLongDate('2026-06-01T10:00:00Z')).toBe('June 01, 2026');
    expect(formatLongDate(null)).toBe('');
    expect(formatLongDate('not a date')).toBe('');
  });

  it('turns a slug into capitalised words', () => {
    expect(labelFromSlug('health-and-beauty')).toBe('Health And Beauty');
    expect(labelFromSlug(undefined)).toBe('');
  });
});
