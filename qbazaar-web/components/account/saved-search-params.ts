import { t } from '@/lib/i18n/messages';
import type { SearchQueryParams } from '@/lib/api/types';

import { formatCount } from './format';
import type { SlugLabels } from './useSlugLabels';

export interface SavedSearchChip {
  label: string;
  value: string;
}

/**
 * Params the search page resolves or sets itself: the ids come back from the
 * slugs, and a restored search starts on its first page.
 */
const NOT_IN_URL = new Set(['category_id', 'location_id', 'page', 'per_page', 'custom_fields']);

/**
 * Build the `/search?...` href from the persisted params. Empty values are
 * dropped so the URL stays clean; the category filters ride the `cf` JSON
 * param the search page reads.
 */
export function savedSearchHref(params: SearchQueryParams): string {
  const sp = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (NOT_IN_URL.has(key) || value === undefined || value === null || value === '') continue;
    sp.set(key, String(value));
  }
  if (params.custom_fields && Object.keys(params.custom_fields).length > 0) {
    sp.set('cf', JSON.stringify(params.custom_fields));
  }
  const qs = sp.toString();
  return qs ? `/search?${qs}` : '/search';
}

function priceValue(min?: number | null, max?: number | null): string | null {
  if (min != null && max != null) {
    return t('account.saved_searches.price_range', { min: formatCount(min), max: formatCount(max) });
  }
  if (max != null) return t('account.saved_searches.price_max', { max: formatCount(max) });
  if (min != null) return t('account.saved_searches.price_min', { min: formatCount(min) });
  return null;
}

/** "Brand: BMW" style chips of 381:8815, one per filter the search keeps. */
export function savedSearchChips(params: SearchQueryParams, labels: SlugLabels): SavedSearchChip[] {
  const chips: SavedSearchChip[] = [];
  const add = (key: string, value: string | null | undefined) => {
    if (value) chips.push({ label: t(`account.saved_searches.params.${key}`), value });
  };

  add('keyword', params.q ? `"${params.q}"` : null);
  add('category', labels.category(params.category_slug));
  add('location', labels.location(params.location_slug));
  add('condition', params.condition ? t(`ads.condition.${params.condition}`) : null);
  add('price', priceValue(params.price_min, params.price_max));
  add('sort', params.sort ? t(`search.sort.${params.sort}`) : null);
  return chips;
}
