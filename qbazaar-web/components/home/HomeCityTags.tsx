'use client';

/**
 * "Find places" collage of the home page, driven by the live Qatar locations
 * tree. Each pill opens the search filtered by `location_slug`.
 */
import type { CSSProperties } from 'react';
import Link from 'next/link';
import { useLayoutEffect, useMemo, useRef, useState } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { useRenderedQatarPlaces } from '@/components/locations/QatarPlacesProvider';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { localized, getLocale, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { Location } from '@/lib/api/types';

/**
 * Where the reference scatters its pills on wide screens: the horizontal
 * centre (from the start edge), the top offset and the tilt of each one.
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

/** Narrower screens wrap the pills in rows that tilt in groups of three. */
const ROW_TILTS = ['[--tilt:-7deg]', 'mt-2 [--tilt:5deg]', 'mt-[3px] [--tilt:-3deg]'] as const;

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

interface Box {
  left: number;
  right: number;
  top: number;
  bottom: number;
}

/** A pill's size and the room its padding and border leave around the label. */
export interface PillMetrics {
  width: number;
  height: number;
  insetX: number;
  insetY: number;
}

const overlap = (a: Box, b: Box) => a.left < b.right && b.left < a.right && a.top < b.bottom && b.top < a.bottom;

/** A label's outer pixels are the empty side bearings of its first and last letters, so a neighbour may touch them. */
const LABEL_BEARING = 3;

/**
 * Whether every label stays readable in the scattered slots: no pill covers
 * another one's label and none leaves the collage. Pills may still touch, as
 * in the reference. Mirroring for Arabic keeps the answer; the small tilts are
 * left out.
 */
export function slotsFit(pills: PillMetrics[], collageWidth: number): boolean {
  const placed = pills.map(({ width, height, insetX, insetY }, index) => {
    const [x, y] = DESKTOP_SLOTS[index];
    const centre = (parseFloat(x) / 100) * collageWidth;
    const top = parseFloat(y);
    const pill = { left: centre - width / 2, right: centre + width / 2, top, bottom: top + height };
    const inset = insetX + LABEL_BEARING;
    const label = { left: pill.left + inset, right: pill.right - inset, top: top + insetY, bottom: pill.bottom - insetY };
    return { pill, label };
  });
  return placed.every(
    ({ pill }, index) =>
      pill.left >= 0 && pill.right <= collageWidth && placed.every((other, j) => j === index || !overlap(pill, other.label)),
  );
}

function pillMetrics(pill: HTMLElement): PillMetrics {
  const style = getComputedStyle(pill);
  const px = (value: string) => parseFloat(value) || 0;
  return {
    width: pill.offsetWidth,
    height: pill.offsetHeight,
    insetX: px(style.paddingLeft) + px(style.borderLeftWidth),
    insetY: px(style.paddingTop) + px(style.borderTopWidth),
  };
}

/**
 * Longer place names, or the wider letter and word spacing a reader may set,
 * can make the scattered pills cover each other's labels. The list then keeps
 * the wrapped rows of the narrower screens. Pills keep their size in either
 * layout, so the answer does not flip back and forth.
 */
function useSlotsFit(places: readonly Place[]) {
  const listRef = useRef<HTMLUListElement>(null);
  const [fits, setFits] = useState(true);

  useLayoutEffect(() => {
    const list = listRef.current;
    if (!list) return;
    const pills = Array.from(list.querySelectorAll<HTMLElement>('a'));
    const check = () => setFits(slotsFit(pills.map(pillMetrics), list.clientWidth));
    check();
    const observer = new ResizeObserver(check);
    observer.observe(list);
    pills.forEach((pill) => observer.observe(pill));
    return () => observer.disconnect();
  }, [places]);

  return { listRef, fits };
}

// Arabic mirrors the tilts along with the positions (--flip). The scattered slots need about 1280 px.
const collage = 'relative mx-auto flex max-w-[1400px] flex-wrap justify-center gap-3 py-1.5 [--flip:1] rtl:[--flip:-1]';
const scatteredCollage = 'min-[1280px]:block min-[1280px]:h-[200px] min-[1280px]:py-0';
const slot = 'rotate-[calc(var(--tilt)*var(--flip))]';
const scatteredSlot =
  'min-[1280px]:absolute min-[1280px]:start-(--slot-x) min-[1280px]:top-(--slot-y) min-[1280px]:mt-0 min-[1280px]:-translate-x-1/2 min-[1280px]:[--tilt:var(--slot-tilt)] rtl:min-[1280px]:translate-x-1/2';

export function HomeCityTags() {
  const locale = getLocale();
  const { data, isLoading, isError } = useQatarLocationsQuery(useRenderedQatarPlaces());
  const places = useMemo(() => (data ? collagePlaces(data, locale) : []), [data, locale]);
  const { listRef, fits } = useSlotsFit(places);

  if (isLoading) {
    return (
      <div className={cn(collage, scatteredCollage)} aria-busy="true">
        {DESKTOP_SLOTS.map((_, i) => (
          <span
            key={i}
            aria-hidden="true"
            style={slotStyle(i)}
            className={cn(pill, ROW_TILTS[i % 3], slot, scatteredSlot, 'h-[45px] w-28 animate-pulse motion-reduce:animate-none')}
          />
        ))}
      </div>
    );
  }
  if (isError || places.length === 0) return null;

  return (
    <ul ref={listRef} className={cn(collage, fits && scatteredCollage)}>
      {places.map((place, i) => (
        <li key={place.slug} style={slotStyle(i)} className={cn(ROW_TILTS[i % 3], slot, fits && scatteredSlot)}>
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
