import { describe, expect, it } from 'vitest';

import { decodeCustomFields } from './search-params';

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
