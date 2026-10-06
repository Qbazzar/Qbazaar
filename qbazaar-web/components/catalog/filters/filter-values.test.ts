import { describe, expect, it } from 'vitest';

import { subtreeCount } from './facet-counts';
import {
  EMPTY_FILTERS,
  countActiveFilters,
  filtersEqual,
  isPriceRangeInvalid,
  normalizeFilters,
  parseNonNegative,
  parsePrice,
} from './filter-values';

describe('parseNonNegative', () => {
  it.each([
    ['250', 250],
    ['1.6', 1.6],
    ['0', 0],
    ['', null],
    ['  ', null],
    ['-1', null],
    ['ten', null],
    [null, null],
  ])('%s -> %s', (raw, expected) => {
    expect(parseNonNegative(raw)).toBe(expected);
  });
});

describe('parsePrice', () => {
  it('keeps whole riyals', () => {
    expect(parsePrice('250')).toBe(250);
    expect(parsePrice('12.5')).toBe(12);
    expect(parsePrice('-1')).toBeNull();
    expect(parsePrice('')).toBeNull();
  });
});

describe('isPriceRangeInvalid', () => {
  it('only flags a maximum below the minimum', () => {
    expect(isPriceRangeInvalid(500, 100)).toBe(true);
    expect(isPriceRangeInvalid(100, 100)).toBe(false);
    expect(isPriceRangeInvalid(null, 100)).toBe(false);
    expect(isPriceRangeInvalid(500, null)).toBe(false);
  });
});

describe('filtersEqual', () => {
  it('compares custom fields regardless of key order', () => {
    const a = { ...EMPTY_FILTERS, customFields: { make: 'Toyota', year: { min: 2015 } } };
    const b = { ...EMPTY_FILTERS, customFields: { year: { min: 2015 }, make: 'Toyota' } };

    expect(filtersEqual(a, b)).toBe(true);
    expect(filtersEqual(a, { ...b, condition: 'used' })).toBe(false);
  });
});

describe('countActiveFilters', () => {
  it('counts a price range once and each custom field', () => {
    expect(countActiveFilters(EMPTY_FILTERS)).toBe(0);
    expect(
      countActiveFilters({ ...EMPTY_FILTERS, location: 'doha', priceMin: 10, priceMax: 90, customFields: { make: 'BMW' } }),
    ).toBe(3);
  });
});

describe('normalizeFilters', () => {
  it('trims text values and drops the empty ones', () => {
    const normalized = normalizeFilters({ ...EMPTY_FILTERS, customFields: { model: '  Corolla ', color: '   ', year: { min: 2010 } } });

    expect(normalized.customFields).toEqual({ model: 'Corolla', year: { min: 2010 } });
  });
});

describe('subtreeCount', () => {
  const tree = {
    slug: 'vehicles',
    children: [
      { slug: 'cars', children: [] },
      { slug: 'boats', children: [{ slug: 'jet-skis', children: [] }] },
    ],
  };

  it('adds the counts of every descendant', () => {
    expect(subtreeCount(tree, { cars: 3, 'jet-skis': 2, electronics: 9 })).toBe(5);
  });

  it('is undefined without facets', () => {
    expect(subtreeCount(tree, null)).toBeUndefined();
  });
});
