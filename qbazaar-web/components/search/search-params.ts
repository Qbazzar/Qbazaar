import type { CustomFieldsFilter, SearchQueryParams } from '@/lib/api/types';

function isRange(value: unknown): value is { min?: number; max?: number } {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
  const bounds = Object.entries(value);
  return bounds.length > 0 && bounds.every(([key, bound]) => (key === 'min' || key === 'max') && typeof bound === 'number' && Number.isFinite(bound));
}

/**
 * The `cf` search param back to category filters. It comes from the URL, so
 * anything that is not a string or a {min, max} range is dropped.
 */
export function decodeCustomFields(raw: string | null): CustomFieldsFilter {
  if (!raw) return {};
  let parsed: unknown;
  try {
    parsed = JSON.parse(raw);
  } catch {
    return {};
  }
  if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return {};

  const filters: CustomFieldsFilter = {};
  for (const [key, value] of Object.entries(parsed)) {
    if ((typeof value === 'string' && value !== '') || isRange(value)) filters[key] = value;
  }
  return filters;
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
