/**
 * Stops of the price slider, in riyals. The API gives no price bounds per
 * category, so one scale serves every page: fine steps for small items, wide
 * ones for cars and property. The first stop means "no minimum" and the last
 * "no maximum".
 */
export const PRICE_STOPS: readonly number[] = [
  0, 50, 100, 250, 500, 1_000, 2_500, 5_000, 10_000, 25_000, 50_000, 100_000, 250_000, 500_000, 1_000_000, 2_500_000, 5_000_000,
];

export const LAST_STOP = PRICE_STOPS.length - 1;

/** Slider position of a minimum price: the highest stop that does not exceed it. */
export function minStopIndex(price: number | null): number {
  if (price === null) return 0;
  const above = PRICE_STOPS.findIndex((stop) => stop > price);
  return above === -1 ? LAST_STOP : Math.max(0, above - 1);
}

/** Slider position of a maximum price: the lowest stop that is not below it. */
export function maxStopIndex(price: number | null): number {
  if (price === null) return LAST_STOP;
  const index = PRICE_STOPS.findIndex((stop) => stop >= price);
  return index === -1 ? LAST_STOP : index;
}

/** The price range of two slider positions; the ends of the scale leave that side open. */
export function rangeFromStops(minIndex: number, maxIndex: number): { priceMin: number | null; priceMax: number | null } {
  return {
    priceMin: minIndex <= 0 ? null : PRICE_STOPS[minIndex],
    priceMax: maxIndex >= LAST_STOP ? null : PRICE_STOPS[maxIndex],
  };
}
