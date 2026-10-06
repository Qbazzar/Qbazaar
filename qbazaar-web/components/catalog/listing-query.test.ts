import { describe, expect, it } from 'vitest';

import { EMPTY_FILTERS } from './filters/filter-values';
import { hasListingParams, listingSearch, parseListingQuery } from './listing-query';

const params = (query: string) => new URLSearchParams(query);

describe('parseListingQuery', () => {
  it('reads the filters, sort, view and page of the URL', () => {
    const query = parseListingQuery(params('category=cars&location=doha&price_min=100&price_max=900&sort=price_asc&view=grid&page=3'));

    expect(query).toEqual({
      filters: { ...EMPTY_FILTERS, category: 'cars', location: 'doha', priceMin: 100, priceMax: 900 },
      sort: 'price_asc',
      view: 'grid',
      page: 3,
    });
  });

  it('falls back to the defaults for missing or invalid values', () => {
    const query = parseListingQuery(params('price_min=-5&price_max=abc&sort=cheapest&view=table&page=0'));

    expect(query.filters.priceMin).toBeNull();
    expect(query.filters.priceMax).toBeNull();
    expect(query.sort).toBe('latest');
    expect(query.view).toBe('list');
    expect(query.page).toBe(1);
  });
});

describe('hasListingParams', () => {
  it.each([
    ['', false],
    ['view=grid', false],
    ['sort=latest', false],
    ['location=doha', true],
    ['price_max=500', true],
    ['sort=oldest', true],
    ['page=2', true],
  ])('%s -> %s', (query, expected) => {
    expect(hasListingParams(params(query))).toBe(expected);
  });
});

describe('listingSearch', () => {
  it('writes the filters and goes back to the first page', () => {
    const search = listingSearch(params('page=4&view=grid'), {
      filters: { ...EMPTY_FILTERS, location: 'al-wakrah', priceMin: 50 },
    });

    expect(search).toBe('?view=grid&location=al-wakrah&price_min=50');
  });

  it('drops the default sort, view and first page from the URL', () => {
    expect(listingSearch(params('sort=oldest&view=grid&page=2'), { sort: 'latest' })).toBe('?view=grid');
    expect(listingSearch(params('view=grid'), { view: 'list' })).toBe('');
    expect(listingSearch(params('page=2'), { page: 1 })).toBe('');
  });

  it('keeps the other filters when only the page changes', () => {
    expect(listingSearch(params('location=doha'), { page: 3 })).toBe('?location=doha&page=3');
  });

  it('clears the filters on reset but keeps unrelated params', () => {
    expect(listingSearch(params('location=doha&price_min=10&ref=home'), { filters: EMPTY_FILTERS })).toBe('?ref=home');
  });
});
