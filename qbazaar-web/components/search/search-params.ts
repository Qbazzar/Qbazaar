import type { CustomFieldsFilter } from '@/lib/api/types';

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
