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
