import { formatAdPrice } from '@/lib/ads/format';
import { formatNumber } from '@/lib/i18n/format';
import { localized, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { findCategoryBySlug } from '@/store/categories';
import { findLocationBySlug } from '@/store/locations';
import type { CategoryNode, Location } from '@/lib/api/types';

import type { FilterValues } from './filter-values';

export interface ActiveFilter {
  key: string;
  label: string;
  /** The applied filters without this one. */
  without: FilterValues;
}

interface Sources {
  categories?: CategoryNode[];
  locations?: Location[];
  locale: Locale;
}

function range(min: number | undefined | null, max: number | undefined | null, format: (value: number) => string): string {
  if (min != null && max != null) return t('catalog.filters.range_between', { min: format(min), max: format(max) }, '{min} – {max}');
  if (min != null) return t('catalog.filters.range_from', { min: format(min) }, 'من {min}');
  return t('catalog.filters.range_to', { max: format(max ?? 0) }, 'حتى {max}');
}

/**
 * The applied filters as removable chips ("DHL ×" in 250:4405), named in the
 * page language. A filter whose name is not loaded yet shows its raw value.
 */
export function activeFilters(values: FilterValues, { categories, locations, locale }: Sources): ActiveFilter[] {
  const chips: ActiveFilter[] = [];
  const category = values.category ? findCategoryBySlug(categories, values.category) : null;

  if (values.category) {
    chips.push({
      key: 'category',
      label: category ? localized(category.name, locale) : values.category,
      without: { ...values, category: null, customFields: {} },
    });
  }
  if (values.location) {
    const place = findLocationBySlug(locations, values.location);
    chips.push({ key: 'location', label: place ? localized(place.name, locale) : values.location, without: { ...values, location: null } });
  }
  if (values.priceMin !== null || values.priceMax !== null) {
    chips.push({
      key: 'price',
      label: range(values.priceMin, values.priceMax, (price) => formatAdPrice({ price, price_type: 'fixed' }, locale)),
      without: { ...values, priceMin: null, priceMax: null },
    });
  }
  if (values.condition) {
    chips.push({ key: 'condition', label: t(`ads.condition.${values.condition}`), without: { ...values, condition: null } });
  }
  if (values.adType) {
    chips.push({ key: 'adType', label: t(`catalog.filters.ad_type.${values.adType}`), without: { ...values, adType: null } });
  }
  if (values.shipping) {
    chips.push({ key: 'shipping', label: t(`catalog.filters.shipping_options.${values.shipping}`), without: { ...values, shipping: null } });
  }

  for (const [key, value] of Object.entries(values.customFields)) {
    const field = category?.custom_fields?.find((candidate) => candidate.key === key);
    const name = field ? localized(field.label, locale) : key;
    const shown =
      typeof value === 'string'
        ? (field?.options_labeled?.find((option) => option.value === value)?.label ?? value)
        : range(value.min, value.max, (number) => formatNumber(number, locale));
    const rest = { ...values.customFields };
    delete rest[key];
    chips.push({ key: `field-${key}`, label: `${name}: ${shown}`, without: { ...values, customFields: rest } });
  }

  return chips;
}
