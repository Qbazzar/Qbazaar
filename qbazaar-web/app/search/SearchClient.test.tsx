import type { ImgHTMLAttributes, ReactNode } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { NuqsTestingAdapter } from 'nuqs/adapters/testing';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const navigation = vi.hoisted(() => ({ search: '' }));

vi.mock('next/navigation', () => ({ useSearchParams: () => new URLSearchParams(navigation.search) }));
vi.mock('next/image', () => ({
  default: ({ fill, ...props }: ImgHTMLAttributes<HTMLImageElement> & { fill?: boolean }) => <img data-fill={fill} {...props} />,
}));
vi.mock('@/components/ads/FavoriteButton', () => ({ FavoriteButton: () => <button type="button">Save</button> }));
vi.mock('@/components/search/SaveSearchButton', () => ({
  SaveSearchButton: ({ params, variant }: { params: object; variant?: string }) =>
    variant ? null : <span data-testid="save-search">{JSON.stringify(params)}</span>,
}));
vi.mock('@/lib/queries/search', () => ({ useSearchQuery: vi.fn() }));
vi.mock('@/lib/queries/ads', () => ({ useFeaturedAdsQuery: () => ({ data: [] }) }));
vi.mock('@/lib/queries/categories', () => ({ useCategoryTreeQuery: () => ({ data: [] }) }));
vi.mock('@/lib/queries/locations', () => ({ useQatarLocationsQuery: () => ({ data: [] }) }));

import { ResultsFocusProvider } from '@/components/catalog/results-focus';
import { setClientLocale } from '@/lib/i18n/locale';
import { useSearchQuery } from '@/lib/queries/search';

import { SearchClient } from './SearchClient';

function results(total: number) {
  return {
    data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 24, total }, facets: null },
    isLoading: false,
    isFetching: false,
    isError: false,
    error: null,
    refetch: vi.fn(),
  } as never;
}

function renderAt(search: string) {
  navigation.search = search;
  const wrapper = ({ children }: { children: ReactNode }) => (
    <NuqsTestingAdapter searchParams={search}>
      <ResultsFocusProvider>{children}</ResultsFocusProvider>
    </NuqsTestingAdapter>
  );
  return render(<SearchClient />, { wrapper });
}

describe('SearchClient', () => {
  beforeEach(() => {
    setClientLocale('en');
    vi.clearAllMocks();
  });

  it('searches by the slugs of the URL and saves the filters without the page', () => {
    vi.mocked(useSearchQuery).mockReturnValue(results(12));
    renderAt('?q=car&category_slug=cars&location_slug=doha&price_min=100&page=2&cf=%7B%22make%22%3A%22BMW%22%7D');

    const filters = { sort: 'latest', q: 'car', category_slug: 'cars', location_slug: 'doha', price_min: 100, custom_fields: { make: 'BMW' } };
    expect(useSearchQuery).toHaveBeenLastCalledWith({ ...filters, page: 2, per_page: 24 });
    expect(JSON.parse(screen.getByTestId('save-search').textContent ?? '')).toEqual(filters);
    expect(screen.getByRole('status')).toHaveTextContent('12 results');
  });

  it('switches to "Search Not Found" when nothing matches', () => {
    vi.mocked(useSearchQuery).mockReturnValue(results(0));
    renderAt('?q=spaceship');

    expect(screen.getByRole('heading', { level: 1, name: 'No results found' })).toBeInTheDocument();
    expect(screen.getByRole('searchbox')).toHaveValue('spaceship');
  });

  it('keeps the focus in the search box when a new query finds nothing either', async () => {
    vi.mocked(useSearchQuery).mockReturnValue(results(0));
    const user = userEvent.setup();
    renderAt('?q=spaceship');
    const box = screen.getByRole('searchbox');

    await user.clear(box);
    await user.type(box, 'rocket{Enter}');

    expect(useSearchQuery).toHaveBeenLastCalledWith(expect.objectContaining({ q: 'rocket' }));
    expect(box).toHaveFocus();
    expect(box).toHaveValue('rocket');
  });

  it('moves the focus to the heading when "Reset filters" still finds nothing', async () => {
    vi.mocked(useSearchQuery).mockReturnValue(results(0));
    const user = userEvent.setup();
    renderAt('?q=spaceship&price_min=100');

    await user.click(screen.getByRole('button', { name: 'Reset filters' }));

    expect(screen.queryByRole('button', { name: 'Reset filters' })).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 1, name: 'No results found' })).toHaveFocus();
  });

  it('sends the distance filter and leaves the place out, since the circle replaces it', () => {
    vi.mocked(useSearchQuery).mockReturnValue(results(12));
    renderAt('?location_slug=doha&lat=25.2854&lng=51.531&radius_km=10');

    const params = vi.mocked(useSearchQuery).mock.lastCall?.[0];
    expect(params).toEqual({ sort: 'latest', lat: 25.2854, lng: 51.531, radius_km: 10, page: 1, per_page: 24 });
  });

  it.each([
    ['a point outside the bounds', '?lat=80&lng=51.5&radius_km=10'],
    ['a radius over the API maximum', '?lat=25.3&lng=51.5&radius_km=500'],
    ['a radius without its point', '?radius_km=10'],
  ])('ignores the distance filter with %s and keeps the place', (_name, search) => {
    vi.mocked(useSearchQuery).mockReturnValue(results(12));
    renderAt(`${search}&location_slug=doha`);

    expect(vi.mocked(useSearchQuery).mock.lastCall?.[0]).toEqual({ sort: 'latest', location_slug: 'doha', page: 1, per_page: 24 });
  });

  it('clears the distance filter with "Reset filters"', async () => {
    vi.mocked(useSearchQuery).mockReturnValue(results(0));
    const user = userEvent.setup();
    renderAt('?lat=25.3&lng=51.5&radius_km=10');
    expect(vi.mocked(useSearchQuery).mock.lastCall?.[0]).toMatchObject({ radius_km: 10 });

    await user.click(screen.getByRole('button', { name: 'Reset filters' }));

    expect(vi.mocked(useSearchQuery).mock.lastCall?.[0]).not.toHaveProperty('radius_km');
    expect(vi.mocked(useSearchQuery).mock.lastCall?.[0]).not.toHaveProperty('lat');
  });

  it('sends the seller and Buy Now of the URL and clears them with "Reset filters"', async () => {
    vi.mocked(useSearchQuery).mockReturnValue(results(0));
    const user = userEvent.setup();
    renderAt('?seller_type=business&buy_now=true');

    expect(vi.mocked(useSearchQuery).mock.lastCall?.[0]).toEqual({ sort: 'latest', seller_type: 'business', buy_now: true, page: 1, per_page: 24 });
    await user.click(screen.getByRole('button', { name: 'Reset filters' }));

    const params = vi.mocked(useSearchQuery).mock.lastCall?.[0];
    expect(params).not.toHaveProperty('seller_type');
    expect(params).not.toHaveProperty('buy_now');
  });

  it('ignores a seller type the API does not know and a Buy Now that is not true', () => {
    vi.mocked(useSearchQuery).mockReturnValue(results(12));
    renderAt('?seller_type=shop&buy_now=false');

    expect(vi.mocked(useSearchQuery).mock.lastCall?.[0]).toEqual({ sort: 'latest', page: 1, per_page: 24 });
  });

  it('does not send a reversed price range, which the API would refuse', () => {
    vi.mocked(useSearchQuery).mockReturnValue(results(12));
    renderAt('?price_min=900&price_max=100');

    expect(vi.mocked(useSearchQuery).mock.lastCall?.[0]).toEqual({ sort: 'latest', page: 1, per_page: 24 });
  });

  it('gives the panel no counts while the previous result set is still on screen', () => {
    vi.mocked(useSearchQuery).mockReturnValue({
      ...(results(12) as object),
      isPlaceholderData: true,
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 24, total: 12, facets: { ad_type: { offering: 3 }, shipping: {}, seller_type: { private: 4 }, buy_now: {}, condition: {} } }, facets: null },
    } as never);
    renderAt('?q=car');

    expect(screen.getAllByRole('radio', { name: 'Private' }).length).toBeGreaterThan(0);
    expect(screen.queryByRole('radio', { name: 'Private (4)' })).not.toBeInTheDocument();
  });

  it('shows the counts once the response of the current filters arrives', () => {
    vi.mocked(useSearchQuery).mockReturnValue({
      ...(results(12) as object),
      isPlaceholderData: false,
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 24, total: 12, facets: { ad_type: {}, shipping: {}, seller_type: { private: 4 }, buy_now: {}, condition: {} } }, facets: null },
    } as never);
    renderAt('?q=car');

    expect(screen.getAllByRole('radio', { name: 'Private (4)' }).length).toBeGreaterThan(0);
  });
});
