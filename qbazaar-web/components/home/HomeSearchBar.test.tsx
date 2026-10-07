import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { Location } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';

const push = vi.hoisted(() => vi.fn());
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));

function place(slug: string, en: string, ar: string, children: Location[] = []): Location {
  return { id: slug, parent_id: null, slug, name: { en, ar }, type: 'city', lat: null, lng: null, children } as Location;
}

const doha = place('doha', 'Doha', 'الدوحة', [place('west-bay', 'West Bay', 'الخليج الغربي')]);
vi.mock('@/lib/queries/locations', () => ({ useQatarLocationsQuery: () => ({ data: [doha] }) }));

import { HomeSearchBar, findPlaceByName, searchHref } from './HomeSearchBar';

describe('searchHref', () => {
  it('keeps only the filled, trimmed fields', () => {
    expect(searchHref('  iphone 15 ', null)).toBe('/search?q=iphone+15');
    expect(searchHref('', 'al-wakrah')).toBe('/search?location_slug=al-wakrah');
    expect(searchHref('sofa', 'doha')).toBe('/search?q=sofa&location_slug=doha');
  });

  it('opens the plain search page when nothing is typed', () => {
    expect(searchHref(' ', null)).toBe('/search');
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

describe('HomeSearchBar', () => {
  beforeEach(() => {
    setClientLocale('en');
    push.mockClear();
  });

  it('is a search landmark with labelled fields that submits to the search page', async () => {
    const user = userEvent.setup();
    render(<HomeSearchBar />);

    expect(screen.getByRole('search')).toBeInTheDocument();
    await user.type(screen.getByRole('searchbox', { name: 'Search' }), 'camera{Enter}');

    expect(push).toHaveBeenCalledWith('/search?q=camera');
  });

  it('filters by the place picked in the location field', async () => {
    const user = userEvent.setup();
    render(<HomeSearchBar />);

    await user.type(screen.getByRole('combobox', { name: 'Location' }), 'West Bay');
    await user.click(screen.getByRole('button', { name: 'Search' }));

    expect(push).toHaveBeenCalledWith('/search?location_slug=west-bay');
  });

  it('links "Choose Category" to the categories page and hides the inert distance field', () => {
    render(<HomeSearchBar />);

    expect(screen.getByRole('link', { name: 'Choose Category' })).toHaveAttribute('href', '/categories');
    expect(screen.queryByText('Distance')?.closest('[aria-hidden="true"]')).not.toBeNull();
  });
});
