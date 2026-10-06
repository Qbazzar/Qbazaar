import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { formatAdAge, formatAdPrice, placeLabel } from './format';

describe('formatAdPrice', () => {
  beforeEach(() => setClientLocale('en'));

  it('prefixes the currency and groups digits', () => {
    expect(formatAdPrice({ price: 285000, price_type: 'fixed' }, 'en')).toBe('QAR 285,000');
  });

  it('uses Arabic digits and currency in Arabic', () => {
    setClientLocale('ar');
    expect(formatAdPrice({ price: 2350, price_type: 'fixed' }, 'ar')).toBe('ر.ق ٢٬٣٥٠');
  });

  it.each([
    [{ price: null, price_type: 'fixed' as const }, 'Contact for price'],
    [{ price: 10, price_type: 'contact' as const }, 'Contact for price'],
    [{ price: 0, price_type: 'free' as const }, 'Free'],
  ])('labels %o as %s', (ad, label) => {
    expect(formatAdPrice(ad, 'en')).toBe(label);
  });
});

describe('formatAdAge', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-10-06T12:00:00Z'));
  });
  afterEach(() => vi.useRealTimers());

  it.each([
    ['2026-10-06T11:30:00Z', '30 minutes ago'],
    ['2026-10-06T07:00:00Z', '5 hours ago'],
    ['2026-10-04T12:00:00Z', '2 days ago'],
  ])('%s -> %s', (iso, label) => {
    expect(formatAdAge(iso, 'en')).toBe(label);
  });

  it('is empty without a date', () => {
    expect(formatAdAge(null, 'en')).toBe('');
  });
});

describe('placeLabel', () => {
  const pearl = { name: { en: 'The Pearl', ar: 'اللؤلؤة' } };

  it('uses the localized place name when the tree has it', () => {
    expect(placeLabel('the-pearl', 'en', pearl)).toBe('The Pearl');
    expect(placeLabel('the-pearl', 'ar', pearl)).toBe('اللؤلؤة');
  });

  it('falls back to the slug as words', () => {
    expect(placeLabel('al-wakrah', 'en')).toBe('al wakrah');
    expect(placeLabel('al-wakrah', 'en', null)).toBe('al wakrah');
  });
});
