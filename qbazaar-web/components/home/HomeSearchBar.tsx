'use client';

/**
 * Hero search bar of the home page (728:44663, open states 489:16010,
 * 492:17733 and 492:18987). "Choose Category" opens the two-level category
 * list and "Distance" the radius list, as in the reference's selects.js; the
 * location suggestions open in the same card. Submitting routes to
 * `/search`. Phones show the keyword field only, as in the design.
 */
import { useId, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';

import { AutocompleteInput } from '@/components/design-system/AutocompleteInput';
import { buttonVariants } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import { LocationPinIcon, SearchIcon } from '@/components/design-system/icons';
import { SelectMenu, type SelectMenuOption } from '@/components/design-system/SelectMenu';
import { useRenderedQatarPlaces } from '@/components/locations/QatarPlacesProvider';
import type { CategoryNode, Location } from '@/lib/api/types';
import { formatNumber } from '@/lib/i18n/format';
import { getLocale, localized, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { useCategoryTreeQuery } from '@/lib/queries/categories';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { cn } from '@/lib/utils';

/**
 * The reference's square fields (the bar is the rounded box): 10 × 14 px padding. Under 1001 px every
 * field may shrink to nothing (responsive.css `form > * { min-width: 0 }`); from 1001 px each keeps the
 * reference's minimum width.
 */
const field = 'flex min-w-0 items-center gap-2 px-3.5 py-2.5';
/**
 * The field in use gets its own ring, next to the brand border of the whole bar. Forced-colors mode drops
 * the ring (a shadow), so outline-hidden draws an outline there instead.
 */
const typingRing = 'focus-within:ring-2 focus-within:ring-qb-brand-active focus-within:ring-inset focus-within:outline-hidden';
const choiceRing = 'outline-none focus-visible:ring-2 focus-visible:ring-qb-brand-active focus-visible:ring-inset focus-visible:outline-hidden';
/**
 * Typed text and the keyword placeholder are 16 px at every width, the placeholder in the browser's
 * #757575; the 1.5 line height (Poppins' normal) stays in Arabic too, where the taller Arabic face would
 * otherwise make the bar taller. The 1 × 2 px padding is the browser's own, so the field is 27 px tall.
 */
const input =
  'w-full min-w-0 bg-transparent px-0.5 py-px text-qb-body leading-[1.5] text-qb-ink outline-none placeholder:text-qb-ink-muted [&::-webkit-search-cancel-button]:hidden';
/** The select labels (.qb-hlabel): 14 px on tablets, 20 px from 1001 px. */
const choiceLabel = 'text-qb-caption leading-[1.5] qb-desktop:text-qb-h5';
/** The field icons are 20 px boxes (22 for the magnifier) that give way first when a field is narrow, as in the reference. */
const fieldIcon = 'size-5 shrink text-qb-icon-faint';
const chevron = 'size-5 text-qb-icon-faint';

/** The radius steps of the design's Distance list, after "All Qatar". */
const DISTANCES_KM = [5, 10, 20, 30, 50, 100, 150, 200] as const;

export interface SearchArea {
  lat: number;
  lng: number;
  radiusKm: number;
}

export interface SearchFields {
  keyword: string;
  categorySlug?: string | null;
  locationSlug?: string | null;
  /** A radius around the chosen place or the visitor. */
  area?: SearchArea | null;
}

/** Four decimals place a point within about 11 m, enough for a radius in kilometres. */
const coordinate = (value: number) => value.toFixed(4);

export function searchHref({ keyword, categorySlug, locationSlug, area }: SearchFields): string {
  const params = new URLSearchParams();
  if (keyword.trim()) params.set('q', keyword.trim());
  if (categorySlug) params.set('category_slug', categorySlug);
  // The place stays next to the radius: /search does not read the radius yet, so it still keeps to the place.
  if (locationSlug) params.set('location_slug', locationSlug);
  if (area) {
    params.set('lat', coordinate(area.lat));
    params.set('lng', coordinate(area.lng));
    params.set('radius_km', String(area.radiusKm));
  }
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

/** The main categories with their sub-categories, as the two levels of the category list. */
export function categoryOptions(tree: CategoryNode[], locale: Locale): SelectMenuOption[] {
  return tree.map((category) => ({
    value: category.slug,
    label: localized(category.name, locale),
    children: category.children.map((child) => ({ value: child.slug, label: localized(child.name, locale) })),
  }));
}

function distanceOptions(locale: Locale): SelectMenuOption[] {
  return [
    { value: '', label: t('home.search.all_qatar', 'كل قطر') },
    ...DISTANCES_KM.map((km) => ({
      value: String(km),
      label: t('home.search.distance_km', { km: formatNumber(km, locale) }, `+${km} كم`),
    })),
  ];
}

type Point = Pick<SearchArea, 'lat' | 'lng'>;

const pointOf = (place: Location | null): Point | null =>
  place?.lat != null && place.lng != null ? { lat: place.lat, lng: place.lng } : null;

/** The visitor's position, or null when the browser has none or the visitor declines. */
function currentPosition(): Promise<Point | null> {
  return new Promise((resolve) => {
    if (!('geolocation' in navigator)) {
      resolve(null);
      return;
    }
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => resolve({ lat: coords.latitude, lng: coords.longitude }),
      () => resolve(null),
      { timeout: 10_000, maximumAge: 300_000 },
    );
  });
}

type LocationError = 'unknown' | 'needed';

const LOCATION_ERRORS: Record<LocationError, () => string> = {
  unknown: () => t('home.search.unknown_place', 'اختر مكاناً من الاقتراحات'),
  needed: () => t('home.search.distance_needs_place', 'اختر موقعاً للبحث حسب المسافة'),
};

export function HomeSearchBar() {
  const router = useRouter();
  const locale = getLocale();
  const [q, setQ] = useState('');
  const [category, setCategory] = useState<string | null>(null);
  const [location, setLocation] = useState('');
  const [distance, setDistance] = useState<string | null>(null);
  const [locationError, setLocationError] = useState<LocationError | null>(null);
  const [locating, setLocating] = useState(false);
  const [categoriesWanted, setCategoriesWanted] = useState(false);
  const locationRef = useRef<HTMLInputElement>(null);
  const errorId = useId();
  // Shares the cached tree with the "Find places" collage below.
  const { data: cities } = useQatarLocationsQuery(useRenderedQatarPlaces());
  // The category tree loads once the visitor heads for the field, not with the page.
  const { data: tree } = useCategoryTreeQuery({ enabled: categoriesWanted });
  const places = useMemo(() => flattenPlaces(cities ?? []), [cities]);
  const suggestions = useMemo(() => places.map((place) => ({ value: place.slug, label: localized(place.name, locale) })), [places, locale]);
  const categories = useMemo(() => categoryOptions(tree ?? [], locale), [tree, locale]);
  const allCategories = useMemo(() => ({ value: '', label: t('home.search.all_categories', 'كل الأقسام') }), []);
  const distances = useMemo(() => distanceOptions(locale), [locale]);

  const showLocationError = (error: LocationError) => {
    setLocationError(error);
    locationRef.current?.focus();
  };

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (locating) return;
    const place = findPlaceByName(places, location);
    // Until the places load, a typed location cannot be checked, so the search runs without it.
    if (location.trim() && places.length > 0 && !place) {
      showLocationError('unknown');
      return;
    }
    const radiusKm = distance ? Number(distance) : null;
    let area: SearchArea | null = null;
    if (radiusKm) {
      // The radius is measured from the chosen place, or from the visitor without one.
      setLocating(true);
      // A typed place that gave no centre must not be swapped for the visitor's position.
      const centre = pointOf(place) ?? (location.trim() ? null : await currentPosition());
      setLocating(false);
      if (!centre) {
        showLocationError('needed');
        return;
      }
      area = { ...centre, radiusKm };
    }
    router.push(searchHref({ keyword: q, categorySlug: category, locationSlug: place?.slug, area }));
  };

  return (
    <div className="mx-auto mt-7 w-full max-w-[1000px] font-qb">
      {/* The form is the reference's rounded white bar; the error line sits under it. */}
      <form
        role="search"
        onSubmit={submit}
        className="flex w-full items-stretch rounded-qb-xl border border-qb-line bg-qb-surface/97 py-1.5 ps-1 pe-1.5 shadow-qb-card has-[input:focus-visible,[role=combobox]:focus-visible]:border-qb-brand qb-tablet:ps-2.5 qb-desktop:flex-wrap qb-desktop:p-2"
      >
        <label className={cn(field, typingRing, 'flex-[2_1_200px] gap-2.5 qb-desktop:min-w-[150px]')}>
          <Icon icon={SearchIcon} size={22} className="shrink text-qb-icon-faint" />
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
        <SelectMenu
          label={t('home.search.category', 'اختر القسم')}
          heading={allCategories}
          options={categories}
          value={category}
          onChange={(option) => setCategory(option.value)}
          onIntent={() => setCategoriesWanted(true)}
          loadingLabel={t('common.loading', 'جارٍ التحميل…')}
          className="hidden min-w-0 flex-[1_1_160px] qb-tablet:flex qb-desktop:min-w-[150px]"
          fieldClassName={cn(field, choiceRing)}
          {...CHOICE_TEXT}
        />
        <Divider />
        {/* The reference's "Location" reads #bdbdbd like the select labels, at the 16 px of typed text. */}
        <TabletField className={cn('relative flex-[1_1_130px] text-qb-icon-faint qb-desktop:min-w-[120px]', typingRing)}>
          <Icon icon={LocationPinIcon} className={fieldIcon} />
          <AutocompleteInput
            ref={locationRef}
            className={cn(input, 'placeholder:text-qb-icon-faint')}
            placeholder={t('home.search.location', 'الموقع')}
            aria-label={t('home.search.location', 'الموقع')}
            aria-invalid={locationError === 'unknown' || undefined}
            aria-describedby={locationError ? errorId : undefined}
            value={location}
            suggestions={suggestions}
            panelClassName="max-h-[min(400px,60vh)]"
            onValueChange={(text) => {
              setLocation(text);
              setLocationError(null);
            }}
          />
        </TabletField>
        <Divider />
        <SelectMenu
          label={t('home.search.distance', 'المسافة')}
          options={distances}
          value={distance}
          onChange={(option) => {
            setDistance(option.value);
            setLocationError(null);
          }}
          className="hidden min-w-0 flex-[1_1_120px] qb-tablet:flex qb-desktop:min-w-[110px]"
          fieldClassName={cn(field, choiceRing)}
          {...CHOICE_TEXT}
        />
        <button
          type="submit"
          aria-disabled={locating || undefined}
          className={cn(buttonVariants({ size: 'md' }), 'm-1 h-auto min-h-11 shrink-0 px-[26px] max-[600px]:not-focus-visible:sr-only')}
        >
          {t('home.search.submit', 'بحث')}
        </button>
      </form>
      {locationError ? (
        <p id={errorId} role="alert" className="mt-2 text-qb-caption text-qb-danger">
          {LOCATION_ERRORS[locationError]()}
        </p>
      ) : null}
    </div>
  );
}

/** The label and chosen-value look of both selects (selects.js setValue: the choice turns #333, same size and weight). */
const CHOICE_TEXT = {
  textClassName: choiceLabel,
  placeholderClassName: 'text-qb-icon-faint',
  valueClassName: 'text-qb-ink-title',
  chevronClassName: chevron,
} as const;

/** A field the phone layout leaves out. */
function TabletField({ className, children }: { className?: string; children: ReactNode }) {
  return <div className={cn(field, 'hidden qb-tablet:flex', className)}>{children}</div>;
}

function Divider() {
  return <span aria-hidden="true" className="my-2 hidden w-px shrink-0 bg-qb-line qb-tablet:block" />;
}
