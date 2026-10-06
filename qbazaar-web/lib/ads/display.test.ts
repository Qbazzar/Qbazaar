import { afterEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';
import type { Ad, Category } from '@/lib/api/types';

import { buildAdSpecSheet, formatAdDate, formatAdPriceLabel, labelFromSlug, tCount } from './display';

const category = {
  custom_fields: [
    { key: 'make', label: { ar: 'الماركة', en: 'Make' }, type: 'select', required: true, options: ['BMW', 'Mercedes'] },
    { key: 'year', label: { ar: 'سنة الصنع', en: 'Year' }, type: 'number', required: true, options: null },
    { key: 'mileage_km', label: { ar: 'الكيلومترات', en: 'Mileage (km)' }, type: 'number', required: false, options: null },
    { key: 'furnished', label: { ar: 'الأثاث', en: 'Furnishing' }, type: 'select', required: false, options: ['semi_furnished'] },
    { key: 'sunroof', label: { ar: 'فتحة سقف', en: 'Sunroof' }, type: 'boolean', required: false, options: null },
    { key: 'towbar', label: { ar: 'خطاف قطر', en: 'Tow bar' }, type: 'boolean', required: false, options: null },
  ],
} as unknown as Category;

function sheetFor(custom_fields: Record<string, unknown> | null, condition: Ad['condition'] = null) {
  return buildAdSpecSheet({ condition, custom_fields: custom_fields as Ad['custom_fields'], category }, 'en');
}

afterEach(() => setClientLocale('ar'));

describe('formatAdPriceLabel', () => {
  it('puts the currency before the amount in English', () => {
    setClientLocale('en');
    expect(formatAdPriceLabel({ price: 285000, price_type: 'fixed' }, 'en')).toBe('QAR 285,000');
  });

  it('writes Arabic digits and the Arabic currency after the amount', () => {
    setClientLocale('ar');
    expect(formatAdPriceLabel({ price: 1500, price_type: 'negotiable' }, 'ar')).toBe('١٬٥٠٠ ر.ق');
  });

  it('says free or contact instead of an amount', () => {
    setClientLocale('en');
    expect(formatAdPriceLabel({ price: 0, price_type: 'free' }, 'en')).toBe('Free');
    expect(formatAdPriceLabel({ price: null, price_type: 'fixed' }, 'en')).toBe('Contact for price');
  });
});

describe('buildAdSpecSheet', () => {
  it('lists the condition first, then the fields in the category order', () => {
    setClientLocale('en');
    const { specs } = sheetFor({ year: 2020, make: 'BMW' }, 'used');

    expect(specs.map((spec) => [spec.label, spec.value])).toEqual([
      ['Condition', 'Used'],
      ['Make', 'BMW'],
      ['Year', '2020'],
    ]);
  });

  it('groups long numbers but keeps a year as it is', () => {
    setClientLocale('en');
    const { specs } = sheetFor({ year: 2020, mileage_km: 126000 });

    expect(specs.find((spec) => spec.key === 'year')?.value).toBe('2020');
    expect(specs.find((spec) => spec.key === 'mileage_km')?.value).toBe('126,000');
  });

  it('turns stored option values into readable text', () => {
    setClientLocale('en');
    expect(sheetFor({ furnished: 'semi_furnished' }).specs[0]?.value).toBe('Semi furnished');
  });

  it('moves the yes/no fields that are set to the features', () => {
    setClientLocale('en');
    const { specs, features } = sheetFor({ sunroof: true, towbar: false, make: 'BMW' });

    expect(features).toEqual(['Sunroof']);
    expect(specs.map((spec) => spec.key)).toEqual(['make']);
  });

  it('keeps values the category no longer defines, under a readable key', () => {
    setClientLocale('en');
    expect(sheetFor({ service_history: 'Full' }).specs).toEqual([
      { key: 'service_history', label: 'Service history', value: 'Full' },
    ]);
  });

  it('skips empty values and copes with an ad without custom fields', () => {
    setClientLocale('en');
    expect(sheetFor({ make: '', year: null }).specs).toEqual([]);
    expect(sheetFor(null)).toEqual({ specs: [], features: [] });
  });
});

describe('formatAdDate', () => {
  it('prints the day on Qatar time', () => {
    expect(formatAdDate('2026-04-11T22:30:00+00:00', 'en')).toBe('Apr 12, 2026');
    expect(formatAdDate(null, 'en')).toBe('');
  });
});

describe('labelFromSlug', () => {
  it('reads a slug as words', () => {
    expect(labelFromSlug('al-wakra-center')).toBe('Al Wakra Center');
    expect(labelFromSlug('')).toBe('');
  });
});

describe('tCount', () => {
  it('uses the English singular for one', () => {
    setClientLocale('en');
    expect(tCount('reviews.count', 1, 'en')).toBe('1 review');
    expect(tCount('reviews.count', 3, 'en')).toBe('3 reviews');
  });

  it('keeps one Arabic form', () => {
    setClientLocale('ar');
    expect(tCount('reviews.count', 1, 'ar')).toBe('١ تقييم');
  });
});
