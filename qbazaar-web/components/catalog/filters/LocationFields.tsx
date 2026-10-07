'use client';

import { useMemo } from 'react';

import { Field } from '@/components/design-system/Field';
import { Select } from '@/components/design-system/Input';
import { formatNumber } from '@/lib/i18n/format';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { findLocationBySlug } from '@/store/locations';
import type { Location } from '@/lib/api/types';

import { subtreeCount } from './facet-counts';

interface LocationFieldsProps {
  /** Slug of the city or of one of its districts. */
  value: string | null;
  onChange: (slug: string | null) => void;
  locations: Location[] | undefined;
  /** Search facet counts by location slug. */
  counts?: Record<string, number> | null;
  /** When the group heading already says "City / Region" (the bottom sheet). */
  hideCityLabel?: boolean;
}

const select = 'h-11 rounded-qb-lg text-qb-caption';

/** City and district pickers ("City / Region", 264:4818); the district list follows the city. */
export function LocationFields({ value, onChange, locations, counts, hideCityLabel = false }: LocationFieldsProps) {
  const locale = getLocale();
  const cities = useMemo(() => locations ?? [], [locations]);

  const { city, districtSlug } = useMemo(() => {
    if (!value) return { city: null, districtSlug: null };
    for (const node of cities) {
      if (node.slug === value) return { city: node, districtSlug: null };
      const district = findLocationBySlug(node.children, value);
      if (district) return { city: node, districtSlug: district.slug };
    }
    return { city: null, districtSlug: null };
  }, [cities, value]);

  const label = (node: Location) => {
    const count = subtreeCount(node, counts);
    const name = localized(node.name, locale);
    return count ? `${name} (${formatNumber(count, locale)})` : name;
  };

  return (
    <div className="flex flex-col gap-4">
      <Field label={t('catalog.filters.city', 'المدينة / المنطقة')} hideLabel={hideCityLabel}>
        {(control) => (
          <Select {...control} value={city?.slug ?? ''} onChange={(event) => onChange(event.target.value || null)} className={select}>
            <option value="">{t('catalog.filters.all_regions', 'كل المناطق')}</option>
            {cities.map((node) => (
              <option key={node.id} value={node.slug}>
                {label(node)}
              </option>
            ))}
          </Select>
        )}
      </Field>
      {city && city.children.length > 0 ? (
        <Field label={t('catalog.filters.district', 'الحي')}>
          {(control) => (
            <Select
              {...control}
              value={districtSlug ?? ''}
              onChange={(event) => onChange(event.target.value || city.slug)}
              className={select}
            >
              <option value="">{t('catalog.filters.all_districts', 'كل الأحياء')}</option>
              {city.children.map((node) => (
                <option key={node.id} value={node.slug}>
                  {label(node)}
                </option>
              ))}
            </Select>
          )}
        </Field>
      ) : null}
    </div>
  );
}
