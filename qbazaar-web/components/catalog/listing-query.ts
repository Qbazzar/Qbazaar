import type { SortMode } from '@/lib/api/types';

import { EMPTY_FILTERS, parsePrice, type FilterValues } from './filters/filter-values';

export type ViewMode = 'list' | 'grid';

export const SORT_MODES: readonly SortMode[] = ['latest', 'oldest', 'price_asc', 'price_desc'];
export const VIEW_MODES: readonly ViewMode[] = ['list', 'grid'];

const DEFAULT_SORT: SortMode = 'latest';
const DEFAULT_VIEW: ViewMode = 'list';

/** URL state of the plain listings (`/ads`, `/c/[slug]`): the filters `GET /ads` supports. */
export interface ListingQuery {
  filters: FilterValues;
  sort: SortMode;
  view: ViewMode;
  page: number;
}

type ParamReader = Pick<URLSearchParams, 'get'>;

export function parseSort(raw: string | null): SortMode {
  return SORT_MODES.find((mode) => mode === raw) ?? DEFAULT_SORT;
}

export function parseView(raw: string | null): ViewMode {
  return VIEW_MODES.find((mode) => mode === raw) ?? DEFAULT_VIEW;
}

export function parsePage(raw: string | null): number {
  const page = Number(raw);
  return Number.isInteger(page) && page > 1 ? page : 1;
}

export function parseListingQuery(params: ParamReader): ListingQuery {
  return {
    filters: {
      ...EMPTY_FILTERS,
      category: params.get('category') || null,
      location: params.get('location') || null,
      priceMin: parsePrice(params.get('price_min')),
      priceMax: parsePrice(params.get('price_max')),
    },
    sort: parseSort(params.get('sort')),
    view: parseView(params.get('view')),
    page: parsePage(params.get('page')),
  };
}

/** True when the URL asks for a filtered, sorted or paged list rather than the category overview. */
export function hasListingParams(params: ParamReader): boolean {
  const query = parseListingQuery(params);
  const { filters } = query;
  return Boolean(filters.location) || filters.priceMin !== null || filters.priceMax !== null || query.sort !== DEFAULT_SORT || query.page > 1;
}

type ListingPatch = Partial<Pick<ListingQuery, 'filters' | 'sort' | 'view' | 'page'>>;

function put(params: URLSearchParams, key: string, value: string | number | null, fallback?: string): void {
  if (value === null || String(value) === '' || String(value) === fallback) params.delete(key);
  else params.set(key, String(value));
}

/**
 * Query string ("" or "?…") for the listing after a change. Unknown params are
 * kept; a new filter or sort goes back to page 1.
 */
export function listingSearch(current: ParamReader & Pick<URLSearchParams, 'toString'>, patch: ListingPatch): string {
  const params = new URLSearchParams(current.toString());
  if (patch.filters) {
    put(params, 'category', patch.filters.category);
    put(params, 'location', patch.filters.location);
    put(params, 'price_min', patch.filters.priceMin);
    put(params, 'price_max', patch.filters.priceMax);
  }
  if (patch.sort) put(params, 'sort', patch.sort, DEFAULT_SORT);
  if (patch.view) put(params, 'view', patch.view, DEFAULT_VIEW);
  if (patch.page !== undefined) put(params, 'page', patch.page, '1');
  else if (patch.filters || patch.sort) params.delete('page');

  const search = params.toString();
  return search ? `?${search}` : '';
}
