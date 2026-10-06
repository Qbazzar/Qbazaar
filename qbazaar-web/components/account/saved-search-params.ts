import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { SearchQueryParams } from '@/lib/api/types';

import { labelFromSlug } from './format';

export interface SavedSearchChip {
  label: string;
  value: string;
}

/**
 * Build the `/search?...` href from the persisted params. We drop keys with
 * `null`/`undefined` so the URL stays clean.
 */
export function savedSearchHref(params: SearchQueryParams): string {
  const sp = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue;
    sp.set(key, String(value));
  }
  const qs = sp.toString();
  return qs ? `/search?${qs}` : '/search';
}

const amount = (value: number) =>
  new Intl.NumberFormat(getLocale() === 'ar' ? 'ar-EG' : 'en-US', { maximumFractionDigits: 0 }).format(value);

function priceValue(min?: number, max?: number): string | null {
  if (min !== undefined && max !== undefined) {
    return t('account.saved_searches.price_range', { min: amount(min), max: amount(max) });
  }
  if (max !== undefined) return t('account.saved_searches.price_max', { max: amount(max) });
  if (min !== undefined) return t('account.saved_searches.price_min', { min: amount(min) });
  return null;
}

/** "Brand: BMW" style chips of 381:8815, one per filter the search keeps. */
export function savedSearchChips(params: SearchQueryParams): SavedSearchChip[] {
  const chips: SavedSearchChip[] = [];
  const add = (key: string, value: string | null | undefined) => {
    if (value) chips.push({ label: t(`account.saved_searches.params.${key}`), value });
  };

  add('keyword', params.q ? `"${params.q}"` : null);
  add('category', labelFromSlug(params.category_slug));
  add('location', labelFromSlug(params.location_slug));
  add('condition', params.condition ? t(`ads.condition.${params.condition}`) : null);
  add('price', priceValue(params.price_min, params.price_max));
  add('sort', params.sort ? t(`search.sort.${params.sort}`) : null);
  return chips;
}
