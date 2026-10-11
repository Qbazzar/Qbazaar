import { describe, expect, it } from 'vitest';

import { decodeCustomFields, isSameSearch } from './search-params';

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
