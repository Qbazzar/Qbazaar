import { describe, expect, it } from 'vitest';

import { EMPTY_FILTERS } from './filters/filter-values';
import { FEED_MAX_PAGE, hasListingParams, listingSearch, parseListingQuery } from './listing-query';

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

  it('stops at the last page the ads feed serves', () => {
    expect(parseListingQuery(params('page=999')).page).toBe(FEED_MAX_PAGE);
  });
});

describe('the catalog filters in the URL', () => {
  it('reads the keyword, offer, seller, shipping, condition, Buy Now and category fields', () => {
    const cf = encodeURIComponent(JSON.stringify({ brand: 'bmw', year: { min: 2015 } }));
    const { filters } = parseListingQuery(
      params(`q=%20sedan%20&ad_type=wanted&seller_type=business&shipping=pickup_only&condition=used&buy_now=true&cf=${cf}`),
    );

    expect(filters).toEqual({
      ...EMPTY_FILTERS,
      keyword: 'sedan',
      adType: 'wanted',
      sellerType: 'business',
      shipping: 'pickup_only',
      condition: 'used',
      buyNow: true,
      customFields: { brand: 'bmw', year: { min: 2015 } },
    });
  });

  it('ignores values the API would refuse', () => {
    const { filters } = parseListingQuery(params('ad_type=sale&seller_type=shop&shipping=post&condition=broken&buy_now=1&cf=%7Bnope'));

    expect(filters).toEqual(EMPTY_FILTERS);
  });

  it('writes them back, drops the cleared ones and returns to page 1', () => {
    const next = { ...EMPTY_FILTERS, keyword: 'sedan', sellerType: 'private' as const, buyNow: true, customFields: { brand: 'bmw' } };
    const written = params(listingSearch(params('page=3&ad_type=wanted&shipping=delivery'), { filters: next }));

    expect(written.get('q')).toBe('sedan');
    expect(written.get('seller_type')).toBe('private');
    expect(written.get('buy_now')).toBe('true');
    expect(JSON.parse(written.get('cf') ?? '{}')).toEqual({ brand: 'bmw' });
    expect(written.has('ad_type')).toBe(false);
    expect(written.has('shipping')).toBe(false);
    expect(written.has('page')).toBe(false);
  });

  it('opens the listing, not the overview, for any of them', () => {
    expect(hasListingParams(params('seller_type=private'))).toBe(true);
    expect(hasListingParams(params('buy_now=true'))).toBe(true);
    expect(hasListingParams(params('q=sedan'))).toBe(true);
    expect(hasListingParams(params('category=cars'))).toBe(false);
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
