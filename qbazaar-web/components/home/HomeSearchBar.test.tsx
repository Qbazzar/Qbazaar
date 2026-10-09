import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { CategoryNode, Location } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';

const push = vi.hoisted(() => vi.fn());
const treeQuery = vi.hoisted(() => vi.fn());
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));

function place(slug: string, en: string, ar: string, children: Location[] = [], point: [number, number] | null = null): Location {
  return { id: slug, parent_id: null, slug, name: { en, ar }, type: 'city', lat: point?.[0] ?? null, lng: point?.[1] ?? null, children } as Location;
}

function category(slug: string, en: string, children: CategoryNode[] = []): CategoryNode {
  return { id: slug, slug, name: { en, ar: en }, children } as unknown as CategoryNode;
}

const doha = place('doha', 'Doha', 'الدوحة', [place('west-bay', 'West Bay', 'الخليج الغربي', [], [25.3215, 51.5311])], [25.2854, 51.531]);
const tree = [category('vehicles', 'Vehicles', [category('cars', 'Cars')]), category('pets', 'Pets')];
vi.mock('@/lib/queries/locations', () => ({ useQatarLocationsQuery: () => ({ data: [doha] }) }));
vi.mock('@/lib/queries/categories', () => ({ useCategoryTreeQuery: treeQuery }));

import { HomeSearchBar, categoryOptions, findPlaceByName, searchHref } from './HomeSearchBar';

describe('searchHref', () => {
  it('keeps only the filled, trimmed fields', () => {
    expect(searchHref({ keyword: '  iphone 15 ' })).toBe('/search?q=iphone+15');
    expect(searchHref({ keyword: '', locationSlug: 'al-wakrah' })).toBe('/search?location_slug=al-wakrah');
    expect(searchHref({ keyword: 'sofa', categorySlug: 'furniture', locationSlug: 'doha' })).toBe(
      '/search?q=sofa&category_slug=furniture&location_slug=doha',
    );
  });

  it('adds a radius around a point to the place', () => {
    expect(searchHref({ keyword: '', locationSlug: 'doha', area: { lat: 25.285412, lng: 51.53104, radiusKm: 5 } })).toBe(
      '/search?location_slug=doha&lat=25.2854&lng=51.5310&radius_km=5',
    );
  });

  it('opens the plain search page when nothing is typed', () => {
    expect(searchHref({ keyword: ' ', categorySlug: '' })).toBe('/search');
  });
});

describe('findPlaceByName', () => {
  const places = [doha, ...doha.children];

  it('matches an English or Arabic name, ignoring case and spaces', () => {
    expect(findPlaceByName(places, ' west bay ')?.slug).toBe('west-bay');
    expect(findPlaceByName(places, 'الدوحة')?.slug).toBe('doha');
  });

  it('finds nothing for unknown or empty text', () => {
    expect(findPlaceByName(places, 'Berlin')).toBeNull();
    expect(findPlaceByName(places, '  ')).toBeNull();
  });
});

describe('categoryOptions', () => {
  it('turns the category tree into the two levels of the list, in the page language', () => {
    expect(categoryOptions(tree, 'en')).toEqual([
      { value: 'vehicles', label: 'Vehicles', children: [{ value: 'cars', label: 'Cars' }] },
      { value: 'pets', label: 'Pets', children: [] },
    ]);
  });
});

describe('HomeSearchBar', () => {
  beforeEach(() => {
    setClientLocale('en');
    push.mockClear();
    treeQuery.mockReset();
    treeQuery.mockImplementation(({ enabled }: { enabled: boolean }) => ({ data: enabled ? tree : undefined }));
  });

  afterEach(() => vi.unstubAllGlobals());

  it('is a search landmark with labelled fields that submits to the search page', async () => {
    const user = userEvent.setup();
    render(<HomeSearchBar />);

    expect(screen.getByRole('search')).toBeInTheDocument();
    await user.type(screen.getByRole('searchbox', { name: 'Search' }), 'camera{Enter}');

    expect(push).toHaveBeenCalledWith('/search?q=camera');
  });

  it('loads the categories only once the visitor heads for the field', async () => {
    const user = userEvent.setup();
    render(<HomeSearchBar />);
    expect(treeQuery).toHaveBeenLastCalledWith({ enabled: false });

    await user.hover(screen.getByRole('combobox', { name: 'Choose Category' }));
    expect(treeQuery).toHaveBeenLastCalledWith({ enabled: true });
  });

  it('searches the category picked in the category list', async () => {
    const user = userEvent.setup();
    render(<HomeSearchBar />);
    const categoryField = screen.getByRole('combobox', { name: 'Choose Category' });

    await user.click(categoryField);
    await user.hover(screen.getByText('Vehicles'));
    await user.click(screen.getByRole('treeitem', { name: 'Cars' }));
    expect(categoryField).toHaveTextContent('Cars');

    await user.click(screen.getByRole('button', { name: 'Search' }));
    expect(push).toHaveBeenCalledWith('/search?category_slug=cars');
  });

  it('filters by the place picked from the location suggestions', async () => {
    const user = userEvent.setup();
    render(<HomeSearchBar />);

    await user.type(screen.getByRole('combobox', { name: 'Location' }), 'West');
    await user.click(screen.getByRole('option', { name: 'West Bay' }));
    await user.click(screen.getByRole('button', { name: 'Search' }));

    expect(push).toHaveBeenCalledWith('/search?location_slug=west-bay');
  });

  it('searches a radius around the chosen place', async () => {
    const user = userEvent.setup();
    render(<HomeSearchBar />);

    await user.type(screen.getByRole('combobox', { name: 'Location' }), 'Doha');
    await user.click(screen.getByRole('combobox', { name: 'Distance' }));
    await user.click(screen.getByRole('option', { name: '+10 km' }));
    expect(screen.getByRole('combobox', { name: 'Distance' })).toHaveTextContent('+10 km');
    await user.click(screen.getByRole('button', { name: 'Search' }));

    expect(push).toHaveBeenCalledWith('/search?location_slug=doha&lat=25.2854&lng=51.5310&radius_km=10');
  });

  it('measures the radius from the visitor without a place, and asks for one when the position is unknown', async () => {
    const user = userEvent.setup();
    const getCurrentPosition = vi.fn((success: PositionCallback) => success({ coords: { latitude: 25.3, longitude: 51.45 } } as GeolocationPosition));
    vi.stubGlobal('navigator', { ...navigator, geolocation: { getCurrentPosition } });
    render(<HomeSearchBar />);

    await user.click(screen.getByRole('combobox', { name: 'Distance' }));
    await user.click(screen.getByRole('option', { name: '+5 km' }));
    await user.click(screen.getByRole('button', { name: 'Search' }));
    expect(push).toHaveBeenCalledWith('/search?lat=25.3000&lng=51.4500&radius_km=5');

    push.mockClear();
    getCurrentPosition.mockImplementation((_success: PositionCallback, failure?: PositionErrorCallback | null) =>
      failure?.({ code: 1 } as GeolocationPositionError),
    );
    await user.click(screen.getByRole('button', { name: 'Search' }));
    expect(push).not.toHaveBeenCalled();
    expect(screen.getByRole('alert')).toHaveTextContent('Choose a location to search by distance');
    expect(screen.getByRole('combobox', { name: 'Location' })).toHaveFocus();
  });

  it('searches all of Qatar when "All Qatar" is the distance', async () => {
    const user = userEvent.setup();
    render(<HomeSearchBar />);

    await user.click(screen.getByRole('combobox', { name: 'Distance' }));
    await user.click(screen.getByRole('option', { name: 'All Qatar' }));
    await user.click(screen.getByRole('button', { name: 'Search' }));

    expect(push).toHaveBeenCalledWith('/search');
  });

  it('asks for a listed place instead of dropping an unknown location', async () => {
    const user = userEvent.setup();
    render(<HomeSearchBar />);
    const location = screen.getByRole('combobox', { name: 'Location' });

    await user.type(location, 'Dohaa');
    await user.click(screen.getByRole('button', { name: 'Search' }));

    expect(push).not.toHaveBeenCalled();
    expect(screen.getByRole('alert')).toHaveTextContent('Choose a place from the suggestions');
    expect(location).toHaveAttribute('aria-invalid', 'true');
    expect(location).toHaveAccessibleDescription('Choose a place from the suggestions');
    expect(location).toHaveFocus();

    await user.type(location, '{Backspace}');
    expect(screen.queryByRole('alert')).toBeNull();
  });
});
