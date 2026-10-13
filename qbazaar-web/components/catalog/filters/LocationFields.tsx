'use client';

import { getLocale, localized, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { findLocationBySlug } from '@/store/locations';
import type { Location } from '@/lib/api/types';

import { subtreeCount } from './facet-counts';
import { OptionList, type FilterOption } from './OptionList';

interface LocationFieldProps {
  /** Id of the group heading that names the field. */
  labelledBy: string;
  /** Slug of a city or of one of its districts. */
  value: string | null;
  onChange: (slug: string) => void;
  locations: Location[] | undefined;
  /** Search facet counts by location slug. */
  counts?: Record<string, number> | null;
}

/** The district a slug names, with its city; null for a city or an unknown slug. */
function findDistrict(cities: Location[], slug: string | null): { city: Location; district: Location } | null {
  if (!slug) return null;
  for (const city of cities) {
    const district = city.slug === slug ? null : findLocationBySlug(city.children, slug);
    if (district) return { city, district };
  }
  return null;
}

function districtLabel(city: Location, district: Location, locale: Locale): string {
  return t('catalog.filters.place_in', { place: localized(district.name, locale), city: localized(city.name, locale) }, '{place}، {city}');
}

/**
 * The cities as radio rows in the desktop filter card ("Cites Services",
 * 69:467), their names in the medium weight the reference gives every city
 * name (typo.js). A district chosen elsewhere (a saved search, a shared link)
 * stays listed first, so the current filter is always visible.
 */
export function CityOptions({ labelledBy, value, onChange, locations, counts, name }: LocationFieldProps & { name: string }) {
  const locale = getLocale();
  const cities = locations ?? [];
  const options: FilterOption[] = cities.map((city) => ({ value: city.slug, label: localized(city.name, locale), count: subtreeCount(city, counts) }));
  const chosen = findDistrict(cities, value);
  if (chosen) {
    options.unshift({ value: chosen.district.slug, label: districtLabel(chosen.city, chosen.district, locale), count: subtreeCount(chosen.district, counts) });
  }

  return <OptionList name={name} labelledBy={labelledBy} options={options} value={value} onChange={onChange} labelClassName="font-medium" />;
}
