'use client';

/**
 * Hero search bar of the home page (728:44663). Routes to
 * `/search?q=...&location_slug=...` on submit, the location being one of the
 * Qatar places suggested under the field; "Choose Category" opens
 * `/categories`, and distance stays a placeholder until search takes a
 * radius. Phones show the keyword field only, as in the design.
 */
import { useId, useMemo, useRef, useState, type ReactNode } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { ChevronDown, MapPin, Search } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import type { Location } from '@/lib/api/types';
import { getLocale, localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { cn } from '@/lib/utils';

const field = 'flex min-w-0 items-center gap-2.5 rounded-qb-md px-3.5 py-2.5';
/** The text field in use gets its own ring, next to the brand border of the whole bar. */
const typingRing = 'focus-within:ring-2 focus-within:ring-qb-brand-active focus-within:ring-inset';
const input =
  'w-full min-w-0 bg-transparent text-qb-body text-qb-ink outline-none placeholder:text-qb-placeholder [&::-webkit-calendar-picker-indicator]:hidden! [&::-webkit-search-cancel-button]:hidden';
/** "Choose Category" and "Distance" stay on one line and read larger on desktop (the reference's .qb-hlabel). */
const choiceLabel = 'whitespace-nowrap text-qb-caption text-qb-placeholder qb-desktop:text-qb-h5';
/** Tablet fields are narrow, so their icons shrink there instead of clipping the text. */
const fieldIcon = 'size-4 text-qb-icon-muted qb-desktop:size-5';

const PLACES_LIST_ID = 'home-search-places';

export function searchHref(keyword: string, locationSlug: string | null): string {
  const params = new URLSearchParams();
  if (keyword.trim()) params.set('q', keyword.trim());
  if (locationSlug) params.set('location_slug', locationSlug);
  const query = params.toString();
  return query ? `/search?${query}` : '/search';
}

/** Every place of the tree, parents before their children. */
function flattenPlaces(nodes: Location[]): Location[] {
  return nodes.flatMap((node) => [node, ...flattenPlaces(node.children)]);
}

/** The place whose English or Arabic name is the typed text, ignoring case and outer spaces. */
export function findPlaceByName(places: Location[], text: string): Location | null {
  const wanted = text.trim().toLocaleLowerCase();
  if (!wanted) return null;
  return places.find(({ name }) => [name.en, name.ar].some((value) => value?.toLocaleLowerCase() === wanted)) ?? null;
}

export function HomeSearchBar() {
  const router = useRouter();
  const locale = getLocale();
  const [q, setQ] = useState('');
  const [location, setLocation] = useState('');
  const [unknownPlace, setUnknownPlace] = useState(false);
  const locationRef = useRef<HTMLInputElement>(null);
  const errorId = useId();
  // Shares the cached tree with the "Find places" collage below.
  const { data: cities } = useQatarLocationsQuery();
  const places = useMemo(() => flattenPlaces(cities ?? []), [cities]);

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    const place = findPlaceByName(places, location);
    // Until the places load, a typed location cannot be checked, so the search runs without it.
    if (location.trim() && places.length > 0 && !place) {
      setUnknownPlace(true);
      locationRef.current?.focus();
      return;
    }
    router.push(searchHref(q, place?.slug ?? null));
  };

  return (
    <form role="search" onSubmit={submit} className="mx-auto mt-7 w-full max-w-[1000px] font-qb">
      <div className="flex w-full items-stretch rounded-qb-xl border border-qb-line bg-qb-surface/97 py-1.5 ps-1 pe-1.5 shadow-qb-card has-[input:focus-visible]:border-qb-brand qb-tablet:ps-2.5 qb-desktop:p-2">
        <label className={cn(field, typingRing, 'flex-[2_1_200px] qb-desktop:min-w-[150px]')}>
          <Icon icon={Search} className="text-qb-ink" />
          <input
            type="search"
            className={input}
            placeholder={t('home.search.keyword', 'ابحث')}
            aria-label={t('home.search.keyword', 'ابحث')}
            value={q}
            onChange={(event) => setQ(event.target.value)}
          />
        </label>
        <Divider />
        {/* Never narrower than its label, so the words are not cut off on small tablets or with wider text spacing. */}
        <Link href="/categories" className={cn(field, 'hidden min-w-fit flex-[1_1_160px] qb-tablet:flex', focusRing)}>
          <span className={choiceLabel}>{t('home.search.category', 'اختر القسم')}</span>
        </Link>
        <Divider />
        <ExtraField className={cn(typingRing, 'flex-[1_1_130px] gap-1.5 px-2.5 qb-desktop:min-w-[120px] qb-desktop:gap-2 qb-desktop:px-3.5')}>
          <Icon icon={MapPin} className={fieldIcon} />
          <input
            ref={locationRef}
            className={input}
            list={PLACES_LIST_ID}
            autoComplete="off"
            placeholder={t('home.search.location', 'الموقع')}
            aria-label={t('home.search.location', 'الموقع')}
            aria-invalid={unknownPlace || undefined}
            aria-describedby={unknownPlace ? errorId : undefined}
            value={location}
            onChange={(event) => {
              setLocation(event.target.value);
              setUnknownPlace(false);
            }}
          />
          <datalist id={PLACES_LIST_ID}>
            {places.map((place) => (
              <option key={place.slug} value={localized(place.name, locale)} />
            ))}
          </datalist>
        </ExtraField>
        <Divider />
        <ExtraField className="min-w-fit flex-[1_1_120px] justify-between gap-1 qb-desktop:gap-2" decorative>
          <span className={choiceLabel}>{t('home.search.distance', 'المسافة')}</span>
          <Icon icon={ChevronDown} className={fieldIcon} />
        </ExtraField>
        <button
          type="submit"
          className={cn(buttonVariants({ size: 'md' }), 'm-1 h-auto min-h-11 shrink-0 px-[26px] max-[600px]:not-focus-visible:sr-only')}
        >
          {t('home.search.submit', 'بحث')}
        </button>
      </div>
      {unknownPlace ? (
        <p id={errorId} role="alert" className="mt-2 text-qb-caption text-qb-danger">
          {t('home.search.unknown_place', 'اختر مكاناً من الاقتراحات')}
        </p>
      ) : null}
    </form>
  );
}

function ExtraField({ className, decorative, children }: { className?: string; decorative?: boolean; children: ReactNode }) {
  return (
    <div aria-hidden={decorative || undefined} className={cn(field, 'hidden qb-tablet:flex', className)}>
      {children}
    </div>
  );
}

function Divider() {
  return <span aria-hidden="true" className="my-2 hidden w-px shrink-0 bg-qb-line qb-tablet:block" />;
}
