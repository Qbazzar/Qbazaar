import type { CustomFieldsFilter, SearchQueryParams } from '@/lib/api/types';

function isRange(value: unknown): value is { min?: number; max?: number } {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
  const bounds = Object.entries(value);
  return bounds.length > 0 && bounds.every(([key, bound]) => (key === 'min' || key === 'max') && typeof bound === 'number' && Number.isFinite(bound));
}

/** The API keeps custom-field keys to `[a-z0-9_]` and accepts at most 10 filters. */
const FIELD_KEY = /^[a-z0-9_]{1,64}$/;
/** Names that match `FIELD_KEY` but would reach the object's prototype. */
const RESERVED_KEYS = new Set(['__proto__', 'constructor', 'prototype']);
const MAX_FIELD_FILTERS = 10;
/** A real `cf` is a few hundred bytes; anything bigger is not worth parsing. */
const MAX_CF_LENGTH = 2048;

/**
 * The `cf` search param back to category filters. It comes from the URL, so
 * it is bounded and checked: anything that is not a string or a {min, max}
 * range, a key the API would refuse, a reversed range (a 422) and entries
 * past the API's limit are dropped.
 */
export function decodeCustomFields(raw: string | null): CustomFieldsFilter {
  const filters: CustomFieldsFilter = Object.create(null);
  if (!raw || raw.length > MAX_CF_LENGTH) return filters;
  let parsed: unknown;
  try {
    parsed = JSON.parse(raw);
  } catch {
    return filters;
  }
  if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return filters;

  for (const [key, value] of Object.entries(parsed)) {
    if (Object.keys(filters).length >= MAX_FIELD_FILTERS) break;
    if (!FIELD_KEY.test(key) || RESERVED_KEYS.has(key)) continue;
    if ((typeof value === 'string' && value !== '') || (isRange(value) && !isReversed(value))) filters[key] = value;
  }
  return filters;
}

function isReversed(range: { min?: number; max?: number }): boolean {
  return range.min !== undefined && range.max !== undefined && range.max < range.min;
}

/** Keeps the filters of the fields the category defines; all of them while its fields are not known yet. */
export function pruneCustomFields(filters: CustomFieldsFilter, allowedKeys: readonly string[] | null): CustomFieldsFilter {
  if (!allowedKeys) return filters;
  return Object.fromEntries(Object.entries(filters).filter(([key]) => allowedKeys.includes(key)));
}

/** Keys that only order or page the results. */
const VIEW_KEYS = new Set(['sort', 'page', 'per_page']);

/** Filters in a stable form: empty values dropped, keys sorted, numbers as strings. */
function stableFilters(value: unknown): unknown {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return String(value);
  return Object.entries(value)
    .filter(([, inner]) => inner !== null && inner !== undefined && inner !== '')
    .map(([key, inner]) => [key, stableFilters(inner)] as const)
    .filter(([, inner]) => !(Array.isArray(inner) && inner.length === 0))
    .sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0));
}

/**
 * Whether two searches ask for the same ads: the same filters, whatever their
 * sort or page and however the API echoes them back (`20` or `"20"`).
 */
export function isSameSearch(a: Record<string, unknown>, b: Record<string, unknown>): boolean {
  const filters = (params: Record<string, unknown>) =>
    JSON.stringify(stableFilters(Object.fromEntries(Object.entries(params).filter(([key]) => !VIEW_KEYS.has(key)))));
  return filters(a) === filters(b);
}

/** Sane bounds for a distance search: around Qatar, and the API's `geo_max_radius_km`. */
const GEO_BOUNDS = { lat: [22, 29], lng: [48, 54], radiusKm: [1, 200] } as const;

function within(value: number | null, [min, max]: readonly [number, number] | readonly number[]): value is number {
  return value !== null && Number.isFinite(value) && value >= min && value <= max;
}

type GeoParams = Pick<SearchQueryParams, 'lat' | 'lng' | 'radius_km'>;

/**
 * The distance filter from the URL: all of `lat`, `lng` and `radius_km`, or
 * nothing, since the API needs the point with the radius. A value outside the
 * bounds drops the whole filter rather than failing the request.
 */
export function readGeoFilter(lat: number | null, lng: number | null, radiusKm: number | null): GeoParams | null {
  if (!within(lat, GEO_BOUNDS.lat) || !within(lng, GEO_BOUNDS.lng) || !within(radiusKm, GEO_BOUNDS.radiusKm)) return null;
  return { lat, lng, radius_km: radiusKm };
}
