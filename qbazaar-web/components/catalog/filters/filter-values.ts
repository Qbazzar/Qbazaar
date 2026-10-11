import type { AdCondition, AdShipping, AdType, CustomFieldsFilter, SellerType } from '@/lib/api/types';

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
  sellerType: SellerType | null;
  /** Only ads a buyer can send a purchase request for. */
  buyNow: boolean;
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
  sellerType: null,
  buyNow: false,
  customFields: {},
};

export const CONDITIONS: readonly AdCondition[] = ['new', 'like_new', 'used'];
export const AD_TYPES: readonly AdType[] = ['offering', 'wanted'];
export const SHIPPING_OPTIONS: readonly AdShipping[] = ['delivery', 'pickup_only'];
export const SELLER_TYPES: readonly SellerType[] = ['private', 'business'];

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

/** The price bounds as they can be sent: a reversed pair (a hand-typed URL) is no filter, as the API would answer 422. */
export function sanePriceRange(min: number | null, max: number | null): { priceMin: number | null; priceMax: number | null } {
  return isRangeInvalid(min, max) ? { priceMin: null, priceMax: null } : { priceMin: min, priceMax: max };
}

/** True when both bounds are set and the maximum is below the minimum. */
export function isRangeInvalid(min: number | null | undefined, max: number | null | undefined): boolean {
  return min != null && max != null && max < min;
}

/** True when the price range or a numeric custom-field range is reversed; such filters cannot apply. */
export function hasInvalidRange(values: FilterValues): boolean {
  return (
    isRangeInvalid(values.priceMin, values.priceMax) ||
    Object.values(values.customFields).some((value) => typeof value === 'object' && isRangeInvalid(value.min, value.max))
  );
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
    a.sellerType === b.sellerType &&
    a.buyNow === b.buyNow &&
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

interface CountOptions {
  /** The search words count as a filter where the page is a category rather than a search. */
  keyword?: boolean;
}

/** How many filters are set (the search words are not one, unless asked); a price range counts once. */
export function countActiveFilters(values: FilterValues, { keyword = false }: CountOptions = {}): number {
  const singles = [values.category, values.location, values.condition, values.adType, values.shipping, values.sellerType];
  const price = values.priceMin !== null || values.priceMax !== null ? 1 : 0;
  const words = keyword && values.keyword.trim() ? 1 : 0;
  return singles.filter(Boolean).length + price + words + Number(values.buyNow) + Object.keys(values.customFields).length;
}
