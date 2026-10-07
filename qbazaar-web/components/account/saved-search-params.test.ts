import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { SearchQueryParams } from '@/lib/api/types';

import { labelFromSlug } from './format';
import { savedSearchChips, savedSearchHref } from './saved-search-params';
import type { SlugLabels } from './useSlugLabels';

const labels: SlugLabels = {
  category: (slug) => (slug === 'apartments-for-rent' ? 'Apartments for Rent' : labelFromSlug(slug)),
  location: (slug) => labelFromSlug(slug),
};

beforeEach(() => setClientLocale('en'));
afterEach(() => setClientLocale('ar'));

describe('savedSearchHref', () => {
  it('restores the search URL without empty params', () => {
    expect(savedSearchHref({ q: 'bmw', category_slug: 'cars', location_slug: '', price_max: 300000 })).toBe(
      '/search?q=bmw&category_slug=cars&price_max=300000',
    );
  });

  it('carries the category filters in the cf param and starts on the first page', () => {
    const href = savedSearchHref({
      category_slug: 'cars',
      category_id: '01J0CAT',
      page: 3,
      per_page: 24,
      custom_fields: { make: 'Toyota', year: { min: 2015 } },
    });
    const url = new URL(href, 'https://qbazaar.qa');
    expect(url.pathname).toBe('/search');
    expect([...url.searchParams.keys()]).toEqual(['category_slug', 'cf']);
    expect(JSON.parse(url.searchParams.get('cf') ?? '')).toEqual({ make: 'Toyota', year: { min: 2015 } });
  });

  it('falls back to the plain search page', () => {
    expect(savedSearchHref({})).toBe('/search');
    expect(savedSearchHref({ custom_fields: {} })).toBe('/search');
  });
});

describe('savedSearchChips', () => {
  it('lists one labelled chip per kept filter, in a stable order', () => {
    expect(
      savedSearchChips(
        {
          sort: 'price_asc',
          price_max: 300000,
          category_slug: 'apartments-for-rent',
          location_slug: 'the-pearl',
          condition: 'used',
          q: 'sea view',
        },
        labels,
      ),
    ).toEqual([
      { label: 'Keyword', value: '"sea view"' },
      { label: 'Category', value: 'Apartments for Rent' },
      { label: 'Location', value: 'The Pearl' },
      { label: 'Condition', value: 'Used' },
      { label: 'Price', value: 'Up to QAR 300,000' },
      { label: 'Sort', value: 'Price: low to high' },
    ]);
  });

  it('describes a price range and a minimum price', () => {
    expect(savedSearchChips({ price_min: 1000, price_max: 5000 }, labels)).toEqual([
      { label: 'Price', value: 'QAR 1,000 – 5,000' },
    ]);
    expect(savedSearchChips({ price_min: 1000 }, labels)).toEqual([{ label: 'Price', value: 'From QAR 1,000' }]);
  });

  it('writes Arabic prices like the listing cards: riyal sign first, Latin digits', () => {
    setClientLocale('ar');
    expect(savedSearchChips({ price_max: 300000 }, labels)).toEqual([
      { label: t('account.saved_searches.params.price'), value: 'حتى ر.ق 300,000' },
    ]);
  });

  it('reads a null price bound, which the API can send, as no bound', () => {
    const params = { price_min: null, price_max: 500 } as unknown as SearchQueryParams;
    expect(savedSearchChips(params, labels)).toEqual([{ label: 'Price', value: 'Up to QAR 500' }]);
    expect(savedSearchChips({ price_min: null, price_max: null } as unknown as SearchQueryParams, labels)).toEqual([]);
  });

  it('has no chips for an unfiltered search', () => {
    expect(savedSearchChips({}, labels)).toEqual([]);
  });
});
