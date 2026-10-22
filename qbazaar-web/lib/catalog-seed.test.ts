import { describe, expect, it } from 'vitest';

import { adKeys } from '@/lib/queries/ads-keys';
import { searchKeys } from '@/lib/queries/search-keys';

import { listingSeedParams, searchSeedParams } from './catalog-seed';

describe('searchSeedParams', () => {
  it('seeds the plain search with the page size the client asks for', () => {
    expect(searchSeedParams({ q: 'سيارة' })).toEqual({ sort: 'latest', q: 'سيارة', page: 1, per_page: 24 });
  });

  it('carries the category, the place, the page and the sort', () => {
    expect(searchSeedParams({ category_slug: 'cars', location_slug: 'doha', page: '3', sort: 'price_asc', view: 'grid' })).toEqual({
      sort: 'price_asc',
      category_slug: 'cars',
      location_slug: 'doha',
      page: 3,
      per_page: 24,
    });
  });

  it('builds the key the client builds, whatever the order of the keys', () => {
    const params = searchSeedParams({ q: 'car' });
    expect(searchKeys.result(params!)).toEqual(searchKeys.result({ per_page: 24, page: 1, q: 'car', sort: 'latest' }));
  });

  it('ignores a key that changes nothing on the page', () => {
    expect(searchSeedParams({ q: 'car', utm_source: 'mail' })).not.toBeNull();
  });

  it.each([
    ['a price filter', { q: 'car', price_min: '100' }],
    ['a custom-field filter', { cf: '{}' }],
    ['a distance filter', { lat: '25.2', lng: '51.5', radius_km: '5' }],
    ['a repeated key', { q: ['a', 'b'] }],
    ['an unknown sort', { sort: 'cheapest' }],
    ['a page that is not a number', { page: 'two' }],
    ['a page of zero', { page: '0' }],
    ['a keyword over the API limit', { q: 'x'.repeat(201) }],
  ])('leaves %s to the client', (_, bag) => {
    expect(searchSeedParams(bag)).toBeNull();
  });
});

describe('listingSeedParams', () => {
  it('seeds the first page of a category without a sort', () => {
    expect(listingSeedParams({}, 'cat-1')).toEqual({ category_id: 'cat-1', page: 1 });
  });

  it('carries a sort and a page, and reads them as the page does', () => {
    expect(listingSeedParams({ sort: 'oldest', page: '2' }, 'cat-1')).toEqual({ category_id: 'cat-1', sort: 'oldest', page: 2 });
    expect(listingSeedParams({ sort: 'nonsense', page: '-4' }, 'cat-1')).toEqual({ category_id: 'cat-1', page: 1 });
  });

  it('builds the key the listing builds, which leaves unset filters out', () => {
    const key = adKeys.list(listingSeedParams({ page: '2' }, 'cat-1')!);
    expect(JSON.stringify(key)).toBe(JSON.stringify(adKeys.list({ category_id: 'cat-1', location_id: undefined, sort: undefined, page: 2 })));
  });

  it('seeds every ad when there is no category', () => {
    expect(listingSeedParams({ view: 'grid' })).toEqual({ page: 1 });
  });

  it.each([['a filter', { condition: 'new' }], ['a keyword', { q: 'car' }], ['a stray category', { category: 'x' }]])('leaves %s to the client', (_, bag) => {
    expect(listingSeedParams(bag, 'cat-1')).toBeNull();
  });
});
