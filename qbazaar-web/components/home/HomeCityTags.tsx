'use client';

/**
 * Home page "find places" word cloud — driven by the live Qatar locations
 * tree instead of a hardcoded list.
 *
 * Pulls top-level cities + a small slice of their districts so the cloud
 * stays varied even after we add more cities. Each chip deep-links into the
 * search surface filtered by `location_slug`, matching how the rest of the
 * app navigates location-scoped browsing.
 */
import Link from 'next/link';
import { useMemo } from 'react';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { localized, getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { Location } from '@/lib/api/types';

const MAX_CHIPS = 10;
const SIZES = ['lg', 'md', 'sm'] as const;

interface Chip {
  slug: string;
  label: string;
  size: (typeof SIZES)[number];
  accent: boolean;
  row: number;
}

/**
 * Flatten the tree to a city-first ordering then trim. The first node in
 * each city group is the city itself, followed by its districts — this gives
 * a natural visual mix of large + small chips when paired with sizes.
 */
function buildChips(nodes: Location[], locale: 'ar' | 'en'): Chip[] {
  const flat: Pick<Chip, 'slug' | 'label'>[] = [];
  for (const city of nodes) {
    flat.push({ slug: city.slug, label: localized(city.name, locale) });
    for (const child of city.children) {
      flat.push({ slug: child.slug, label: localized(child.name, locale) });
    }
  }
  return flat.slice(0, MAX_CHIPS).map((item, i) => ({
    ...item,
    size: SIZES[i % SIZES.length],
    accent: i % 4 === 0,
    row: i + 1,
  }));
}

export function HomeCityTags() {
  const locale = getLocale();
  const { data, isLoading, isError } = useQatarLocationsQuery();

  const chips = useMemo(
    () => (data ? buildChips(data, locale) : []),
    [data, locale],
  );

  if (isLoading) {
    return (
      <div className="city-tags" aria-busy="true">
        {Array.from({ length: MAX_CHIPS }).map((_, i) => (
          <span
            key={i}
            className={`city-tag city-tag--${SIZES[i % SIZES.length]} animate-pulse bg-cream-200/60`}
            style={{ minWidth: 80, height: 28 }}
            aria-hidden
          />
        ))}
      </div>
    );
  }

  if (isError || chips.length === 0) {
    return null;
  }

  return (
    <div className="city-tags">
      {chips.map((chip) => (
        <Link
          key={chip.slug}
          href={`/search?location_slug=${encodeURIComponent(chip.slug)}`}
          className={`city-tag city-tag--${chip.size}${
            chip.accent ? ' city-tag--accent' : ''
          } city-tag--r${chip.row}`}
          aria-label={t(
            'home.cities.browse_aria',
            { city: chip.label },
            `تصفح الإعلانات في ${chip.label}`,
          )}
        >
          {chip.label}
        </Link>
      ))}
    </div>
  );
}
