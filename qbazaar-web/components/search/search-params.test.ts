import { describe, expect, it } from 'vitest';

import { decodeCustomFields, isSameSearch, pruneCustomFields } from './search-params';

describe('decodeCustomFields', () => {
  it('reads values and ranges back from the URL param', () => {
    expect(decodeCustomFields('{"make":"Toyota","year":{"min":2015,"max":2020}}')).toEqual({
      make: 'Toyota',
      year: { min: 2015, max: 2020 },
    });
  });

  it.each([null, '', 'not json', '[1,2]', '"text"', '42'])('ignores %s', (raw) => {
    expect(decodeCustomFields(raw)).toEqual({});
  });

  it('drops entries that are neither text nor a numeric range', () => {
    expect(decodeCustomFields('{"a":"","b":5,"c":{},"d":{"min":"1"},"e":{"from":1},"f":{"max":9}}')).toEqual({ f: { max: 9 } });
  });

  it('ignores a param over 2 KB without parsing it', () => {
    expect(decodeCustomFields(JSON.stringify({ make: 'x'.repeat(3000) }))).toEqual({});
  });

  it('refuses __proto__ and keys the API would refuse', () => {
    const filters = decodeCustomFields('{"__proto__":"x","Bad Key":"x","UPPER":"x","ok_key1":"x","a-b":"x"}');

    expect(Object.keys(filters)).toEqual(['ok_key1']);
    expect(Object.getPrototypeOf(filters)).toBeNull();
    expect(({} as Record<string, unknown>).x).toBeUndefined();
  });

  it('keeps at most 10 filters, as the API does', () => {
    const many = Object.fromEntries(Array.from({ length: 14 }, (_, index) => [`f${index}`, 'v']));

    expect(Object.keys(decodeCustomFields(JSON.stringify(many)))).toHaveLength(10);
  });

  it('drops a reversed range, which would be a 422, and keeps a valid or one-sided one', () => {
    expect(decodeCustomFields('{"year":{"min":2020,"max":2010},"km":{"min":1,"max":1},"price":{"min":5}}')).toEqual({
      km: { min: 1, max: 1 },
      price: { min: 5 },
    });
  });
});

describe('isSameSearch', () => {
  it('matches the same filters whatever the sort, the key order or the number type', () => {
    expect(
      isSameSearch(
        { q: 'bmw', price_min: 20, custom_fields: { year: { min: 2015 }, make: 'BMW' }, sort: 'latest' },
        { custom_fields: { make: 'BMW', year: { min: '2015' } }, price_min: '20', q: 'bmw', sort: 'price_asc' },
      ),
    ).toBe(true);
    expect(isSameSearch({ category_slug: 'cars', custom_fields: {}, q: '' }, { category_slug: 'cars' })).toBe(true);
  });

  it('tells searches with other filters apart', () => {
    expect(isSameSearch({ category_slug: 'cars' }, { category_slug: 'cars', location_slug: 'doha' })).toBe(false);
    expect(isSameSearch({ q: 'bmw' }, { q: 'audi' })).toBe(false);
  });
});

describe('pruneCustomFields', () => {
  it('keeps the fields of the category only, and everything while they are not known', () => {
    const filters = { make: 'bmw', stale: 'x' };

    expect(pruneCustomFields(filters, ['make'])).toEqual({ make: 'bmw' });
    expect(pruneCustomFields(filters, [])).toEqual({});
    expect(pruneCustomFields(filters, null)).toBe(filters);
  });
});
