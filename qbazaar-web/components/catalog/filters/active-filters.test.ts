import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';
import type { CategoryNode, Location } from '@/lib/api/types';

import { activeFilters } from './active-filters';
import { EMPTY_FILTERS } from './filter-values';

const cars: CategoryNode = {
  id: 'cars',
  parent_id: null,
  slug: 'cars',
  name: { en: 'Cars', ar: 'سيارات' },
  description: null,
  icon: null,
  order: 0,
  is_active: true,
  custom_fields: [{ key: 'make', label: { en: 'Make', ar: 'الماركة' }, type: 'select', required: true, options: ['BMW'] }],
  custom_filters: null,
  ads_count: 0,
  today_count: 0,
  created_at: '',
  updated_at: '',
  children: [],
};
const doha: Location = { id: 'doha', parent_id: null, slug: 'doha', name: { en: 'Doha', ar: 'الدوحة' }, type: 'city', lat: null, lng: null, children: [] };

describe('activeFilters', () => {
  beforeEach(() => setClientLocale('en'));

  it('names each applied filter and drops it from the values it applies', () => {
    const values = { ...EMPTY_FILTERS, category: 'cars', location: 'doha', priceMin: 100, priceMax: 900, condition: 'used' as const, customFields: { make: 'BMW' } };
    const chips = activeFilters(values, { categories: [cars], locations: [doha], locale: 'en' });

    expect(chips.map((chip) => chip.label)).toEqual(['Cars', 'Doha', 'QAR 100 – QAR 900', 'Used', 'Make: BMW']);
    expect(chips[0].without).toEqual({ ...values, category: null, customFields: {} });
    expect(chips[2].without).toMatchObject({ priceMin: null, priceMax: null, location: 'doha' });
    expect(chips[4].without.customFields).toEqual({});
  });

  it('labels open-ended price ranges', () => {
    expect(activeFilters({ ...EMPTY_FILTERS, priceMin: 50 }, { locale: 'en' })[0].label).toBe('From QAR 50');
    expect(activeFilters({ ...EMPTY_FILTERS, priceMax: 50 }, { locale: 'en' })[0].label).toBe('Up to QAR 50');
  });

  it('falls back to the raw value before the trees load, and ignores the search words', () => {
    const chips = activeFilters({ ...EMPTY_FILTERS, keyword: 'bmw', location: 'west-bay', shipping: 'delivery' }, { locale: 'en' });

    expect(chips.map((chip) => chip.label)).toEqual(['west-bay', 'Shipping Available']);
  });

  it('speaks Arabic in Arabic', () => {
    setClientLocale('ar');
    const [chip] = activeFilters({ ...EMPTY_FILTERS, location: 'doha' }, { locations: [doha], locale: 'ar' });

    expect(chip.label).toBe('الدوحة');
  });
});
