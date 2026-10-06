import { afterEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { formatAdPrice, formatCount, labelFromSlug, maskPhone } from './format';

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
  it('puts the currency before a grouped amount in English', () => {
    setClientLocale('en');
    expect(formatAdPrice(287000, 'fixed')).toBe('QAR 287,000');
    expect(formatAdPrice(955.4, 'negotiable')).toBe('QAR 955');
  });

  it('names free and contact-for-price ads instead of a number', () => {
    setClientLocale('en');
    expect(formatAdPrice(0, 'free')).toBe('Free');
    expect(formatAdPrice(null, 'fixed')).toBe('Contact for price');
    expect(formatAdPrice(500, 'contact')).toBe('Contact for price');
  });

  it('uses Arabic digits and the riyal sign in Arabic', () => {
    setClientLocale('ar');
    expect(formatAdPrice(1200, 'fixed')).toBe('١٬٢٠٠ ر.ق');
  });
});

describe('formatCount and labelFromSlug', () => {
  it('groups counts in the active locale', () => {
    setClientLocale('en');
    expect(formatCount(1525)).toBe('1,525');
  });

  it('turns a slug into words', () => {
    expect(labelFromSlug('health-and-beauty')).toBe('health and beauty');
    expect(labelFromSlug(undefined)).toBe('');
  });
});
