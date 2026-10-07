'use client';

/**
 * "Find places" collage of the home page, driven by the live Qatar locations
 * tree. Each pill opens the search filtered by `location_slug`.
 */
import type { CSSProperties } from 'react';
import Link from 'next/link';
import { useMemo } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { localized, getLocale, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { Location } from '@/lib/api/types';

/**
 * Where the reference scatters its pills on desktop: the horizontal centre
 * (from the start edge), the top offset and the tilt of each one.
 */
const DESKTOP_SLOTS = [
  ['5%', '66px', '-16deg'],
  ['15%', '22px', '-9deg'],
  ['12%', '104px', '-5deg'],
  ['26%', '14px', '-4deg'],
  ['21%', '78px', '-12deg'],
  ['31%', '92px', '-13deg'],
  ['39%', '24px', '9deg'],
  ['43%', '104px', '-3deg'],
  ['51%', '18px', '-2deg'],
  ['57%', '104px', '0deg'],
  ['66%', '22px', '11deg'],
  ['78%', '26px', '-5deg'],
  ['68%', '104px', '2deg'],
  ['81%', '104px', '-2deg'],
  ['91%', '20px', '13deg'],
  ['94%', '104px', '-2deg'],
] as const;

/** Under 1000 px the pills wrap in rows and tilt in groups of three. */
const ROW_TILTS = ['-rotate-7', 'mt-2 rotate-5', 'mt-[3px] -rotate-3'] as const;

const pill =
  'inline-block rounded-qb-pill border border-qb-line bg-qb-surface px-5 py-2.5 text-qb-h5 leading-[23px] font-medium whitespace-nowrap text-qb-black shadow-qb-float';

interface Place {
  slug: string;
  label: string;
}

/** Cities first, then their districts, up to one place per collage slot. */
export function collagePlaces(cities: Location[], locale: Locale): Place[] {
  const districts = cities.flatMap((city) => city.children);
  return [...cities, ...districts]
    .slice(0, DESKTOP_SLOTS.length)
    .map((node) => ({ slug: node.slug, label: localized(node.name, locale) }));
}

function slotStyle(index: number): CSSProperties {
  const [x, y, tilt] = DESKTOP_SLOTS[index];
  return { '--slot-x': x, '--slot-y': y, '--slot-tilt': tilt } as CSSProperties;
}

const collage =
  'relative mx-auto flex max-w-[1400px] flex-wrap justify-center gap-3 py-1.5 qb-desktop:block qb-desktop:h-[200px] qb-desktop:py-0';
const slot =
  'qb-desktop:absolute qb-desktop:start-(--slot-x) qb-desktop:top-(--slot-y) qb-desktop:mt-0 qb-desktop:-translate-x-1/2 qb-desktop:rotate-(--slot-tilt) rtl:qb-desktop:translate-x-1/2';

export function HomeCityTags() {
  const locale = getLocale();
  const { data, isLoading, isError } = useQatarLocationsQuery();
  const places = useMemo(() => (data ? collagePlaces(data, locale) : []), [data, locale]);

  if (isLoading) {
    return (
      <div className={collage} aria-busy="true">
        {DESKTOP_SLOTS.map((_, i) => (
          <span key={i} aria-hidden="true" style={slotStyle(i)} className={cn(pill, ROW_TILTS[i % 3], slot, 'h-[45px] w-28 animate-pulse')} />
        ))}
      </div>
    );
  }
  if (isError || places.length === 0) return null;

  return (
    <ul className={collage}>
      {places.map((place, i) => (
        <li key={place.slug} style={slotStyle(i)} className={cn(ROW_TILTS[i % 3], slot)}>
          <Link
            href={`/search?location_slug=${encodeURIComponent(place.slug)}`}
            aria-label={t('home.cities.browse_aria', { city: place.label }, `تصفح الإعلانات في ${place.label}`)}
            className={cn(pill, 'hover:border-qb-brand', focusRing)}
          >
            {place.label}
          </Link>
        </li>
      ))}
    </ul>
  );
}
