'use client';

import { formatNumber } from '@/lib/i18n/format';
import { getLocale, localized, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { findLocationBySlug } from '@/store/locations';
import type { Location } from '@/lib/api/types';

import { subtreeCount } from './facet-counts';
import { OptionList, type FilterOption } from './OptionList';
import { SelectField, type SelectOption } from './SelectField';

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

function placeName(node: Location, locale: Locale, counts: LocationFieldProps['counts']): string {
  const name = localized(node.name, locale);
  const count = subtreeCount(node, counts);
  return count ? `${name} (${formatNumber(count, locale)})` : name;
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
 * name (typo.js). A district chosen elsewhere (the sheet, a saved search)
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

/**
 * "City / Region" select of the filter sheet (618:26974, 264:4818). It lists
 * the cities; typing in its search box also finds their districts.
 */
export function CitySelect({ labelledBy, value, onChange, locations, counts }: LocationFieldProps) {
  const locale = getLocale();
  const cities = locations ?? [];
  const options: SelectOption[] = cities.map((city) => ({ value: city.slug, label: placeName(city, locale, counts) }));
  const districts: SelectOption[] = cities.flatMap((city) =>
    city.children.map((district) => {
      const count = subtreeCount(district, counts);
      const label = districtLabel(city, district, locale);
      return { value: district.slug, label: count ? `${label} (${formatNumber(count, locale)})` : label, searchText: localized(district.name, locale) };
    }),
  );

  return (
    <SelectField
      labelledBy={labelledBy}
      options={options}
      searchOnlyOptions={districts}
      value={value}
      onChange={onChange}
      placeholder={t('catalog.filters.all_regions', 'كل المناطق')}
    />
  );
}
