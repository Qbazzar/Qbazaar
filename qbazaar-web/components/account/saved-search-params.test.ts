import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { savedSearchChips, savedSearchHref } from './saved-search-params';

beforeEach(() => setClientLocale('en'));
afterEach(() => setClientLocale('ar'));

describe('savedSearchHref', () => {
  it('restores the search URL without empty params', () => {
    expect(savedSearchHref({ q: 'bmw', category_slug: 'cars', location_slug: '', price_max: 300000 })).toBe(
      '/search?q=bmw&category_slug=cars&price_max=300000',
    );
  });

  it('falls back to the plain search page', () => {
    expect(savedSearchHref({})).toBe('/search');
  });
});

describe('savedSearchChips', () => {
  it('lists one labelled chip per kept filter, in a stable order', () => {
    expect(
      savedSearchChips({
        sort: 'price_asc',
        price_max: 300000,
        category_slug: 'apartments-for-rent',
        location_slug: 'the-pearl',
        condition: 'used',
        q: 'sea view',
      }),
    ).toEqual([
      { label: 'Keyword', value: '"sea view"' },
      { label: 'Category', value: 'apartments for rent' },
      { label: 'Location', value: 'the pearl' },
      { label: 'Condition', value: 'Used' },
      { label: 'Price', value: 'Up to QAR 300,000' },
      { label: 'Sort', value: 'Price: low to high' },
    ]);
  });

  it('describes a price range and a minimum price', () => {
    expect(savedSearchChips({ price_min: 1000, price_max: 5000 })).toEqual([
      { label: 'Price', value: 'QAR 1,000 – 5,000' },
    ]);
    expect(savedSearchChips({ price_min: 1000 })).toEqual([{ label: 'Price', value: 'From QAR 1,000' }]);
  });

  it('has no chips for an unfiltered search', () => {
    expect(savedSearchChips({})).toEqual([]);
  });
});
