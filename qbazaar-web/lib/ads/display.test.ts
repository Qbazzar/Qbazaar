import { afterEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';
import type { Ad, Category } from '@/lib/api/types';

import { buildAdSpecSheet, formatAdDate, formatRating, formatTimeAgo } from './display';

const category = {
  custom_fields: [
    { key: 'make', label: { ar: 'الماركة', en: 'Make' }, type: 'select', required: true, options: ['BMW', 'Mercedes'] },
    { key: 'year', label: { ar: 'سنة الصنع', en: 'Year' }, type: 'number', required: true, options: null },
    { key: 'mileage_km', label: { ar: 'الكيلومترات', en: 'Mileage (km)' }, type: 'number', required: false, options: null },
    { key: 'furnished', label: { ar: 'الأثاث', en: 'Furnishing' }, type: 'select', required: false, options: ['semi_furnished'] },
    {
      key: 'fuel',
      label: { ar: 'الوقود', en: 'Fuel' },
      type: 'select',
      required: false,
      options: ['petrol', 'hybrid'],
      options_labeled: [{ value: 'petrol', label: 'Gasoline' }],
    },
    { key: 'sunroof', label: { ar: 'فتحة سقف', en: 'Sunroof' }, type: 'boolean', required: false, options: null },
    { key: 'towbar', label: { ar: 'خطاف قطر', en: 'Tow bar' }, type: 'boolean', required: false, options: null },
  ],
} as unknown as Category;

function sheetFor(custom_fields: Record<string, unknown> | null, condition: Ad['condition'] = null) {
  return buildAdSpecSheet({ condition, custom_fields: custom_fields as Ad['custom_fields'], category }, 'en');
}

afterEach(() => setClientLocale('ar'));

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

  it('shows an option by the label the API sends, falling back to the readable value', () => {
    setClientLocale('en');
    expect(sheetFor({ fuel: 'petrol' }).specs[0]?.value).toBe('Gasoline');
    expect(sheetFor({ fuel: ['petrol', 'hybrid'] }).specs[0]?.value).toBe('Gasoline, Hybrid');
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

  it('writes Latin digits on Arabic pages', () => {
    expect(formatAdDate('2026-04-11T22:30:00+00:00', 'ar')).toMatch(/^12 .+ 2026$/);
  });
});

describe('formatRating', () => {
  it('keeps one decimal at most, in Latin digits in both languages', () => {
    expect(formatRating(4.46, 'en')).toBe('4.5');
    expect(formatRating(4, 'ar')).toBe('4');
    expect(formatRating(4.25, 'ar')).toBe('4.3');
  });
});

describe('formatTimeAgo', () => {
  it('says how long ago with Latin digits, then falls back to the full date', () => {
    const now = Date.now();
    expect(formatTimeAgo(new Date(now - 3 * 60 * 60 * 1000).toISOString(), 'en')).toBe('3 hours ago');
    expect(formatTimeAgo(new Date(now - 3 * 60 * 60 * 1000).toISOString(), 'ar')).toMatch(/3/);
    expect(formatTimeAgo('2024-03-12T10:00:00+00:00', 'en')).toBe('Mar 12, 2024');
    expect(formatTimeAgo(null, 'en')).toBe('');
  });
});
