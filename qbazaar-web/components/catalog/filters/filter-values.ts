import type { AdCondition, AdShipping, AdType, CustomFieldsFilter } from '@/lib/api/types';

/** Every filter a catalog page can offer; each page shows the ones its endpoint supports. */
export interface FilterValues {
  /** The search words (search page only); "Reset All" keeps them. */
  keyword: string;
  category: string | null;
  location: string | null;
  priceMin: number | null;
  priceMax: number | null;
  condition: AdCondition | null;
  adType: AdType | null;
  shipping: AdShipping | null;
  customFields: CustomFieldsFilter;
}

export const EMPTY_FILTERS: FilterValues = {
  keyword: '',
  category: null,
  location: null,
  priceMin: null,
  priceMax: null,
  condition: null,
  adType: null,
  shipping: null,
  customFields: {},
};

export const CONDITIONS: readonly AdCondition[] = ['new', 'like_new', 'used'];
export const AD_TYPES: readonly AdType[] = ['offering', 'wanted'];
export const SHIPPING_OPTIONS: readonly AdShipping[] = ['delivery', 'pickup_only'];

/** A non-negative number from a query or input value, or null when there is none. */
export function parseNonNegative(raw: string | null | undefined): number | null {
  if (raw == null || raw.trim() === '') return null;
  const value = Number(raw);
  return Number.isFinite(value) && value >= 0 ? value : null;
}

/** A price bound in whole riyals: money never travels as a fraction. */
export function parsePrice(raw: string | null | undefined): number | null {
  const value = parseNonNegative(raw);
  return value === null ? null : Math.floor(value);
}

/** True when both bounds are set and the maximum is below the minimum. */
export function isPriceRangeInvalid(min: number | null, max: number | null): boolean {
  return min !== null && max !== null && max < min;
}

function sameCustomFields(a: CustomFieldsFilter, b: CustomFieldsFilter): boolean {
  return JSON.stringify(sortKeys(a)) === JSON.stringify(sortKeys(b));
}

function sortKeys(value: CustomFieldsFilter): CustomFieldsFilter {
  return Object.fromEntries(Object.entries(value).sort(([a], [b]) => a.localeCompare(b)));
}

export function filtersEqual(a: FilterValues, b: FilterValues): boolean {
  return (
    a.keyword.trim() === b.keyword.trim() &&
    a.category === b.category &&
    a.location === b.location &&
    a.priceMin === b.priceMin &&
    a.priceMax === b.priceMax &&
    a.condition === b.condition &&
    a.adType === b.adType &&
    a.shipping === b.shipping &&
    sameCustomFields(a.customFields, b.customFields)
  );
}

/** Trims text values and drops the empty ones, as typed values reach the URL. */
export function normalizeFilters(values: FilterValues): FilterValues {
  const customFields: CustomFieldsFilter = {};
  for (const [key, value] of Object.entries(values.customFields)) {
    if (typeof value === 'string') {
      if (value.trim()) customFields[key] = value.trim();
    } else if (value.min !== undefined || value.max !== undefined) {
      customFields[key] = value;
    }
  }
  return { ...values, keyword: values.keyword.trim(), customFields };
}

/** How many filters are set (the search words are not one); a price range counts once. */
export function countActiveFilters(values: FilterValues): number {
  const singles = [values.category, values.location, values.condition, values.adType, values.shipping];
  const price = values.priceMin !== null || values.priceMax !== null ? 1 : 0;
  return singles.filter(Boolean).length + price + Object.keys(values.customFields).length;
}
