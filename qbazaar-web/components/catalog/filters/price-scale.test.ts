import { describe, expect, it } from 'vitest';

import { LAST_STOP, PRICE_STOPS, maxStopIndex, minStopIndex, rangeFromStops } from './price-scale';

describe('price scale', () => {
  it('puts an open range at the two ends of the slider', () => {
    expect(minStopIndex(null)).toBe(0);
    expect(maxStopIndex(null)).toBe(LAST_STOP);
    expect(rangeFromStops(0, LAST_STOP)).toEqual({ priceMin: null, priceMax: null });
  });

  it('places a price between two stops inside the range it keeps', () => {
    expect(PRICE_STOPS[minStopIndex(1_234)]).toBe(1_000);
    expect(PRICE_STOPS[maxStopIndex(1_234)]).toBe(2_500);
    expect(PRICE_STOPS[minStopIndex(100)]).toBe(100);
    expect(PRICE_STOPS[maxStopIndex(100)]).toBe(100);
  });

  it('keeps prices beyond the scale on its last stop', () => {
    expect(minStopIndex(9_000_000)).toBe(LAST_STOP);
    expect(maxStopIndex(9_000_000)).toBe(LAST_STOP);
  });

  it('turns two inner stops into a closed range', () => {
    expect(rangeFromStops(2, 5)).toEqual({ priceMin: 100, priceMax: 1_000 });
  });
});
