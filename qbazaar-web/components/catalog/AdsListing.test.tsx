import type { ImgHTMLAttributes, ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const navigation = vi.hoisted(() => ({ search: '' }));

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  usePathname: () => '/ads',
  useSearchParams: () => new URLSearchParams(navigation.search),
}));
vi.mock('next/image', () => ({
  default: ({ fill, ...props }: ImgHTMLAttributes<HTMLImageElement> & { fill?: boolean }) => <img data-fill={fill} {...props} />,
}));
vi.mock('@/components/ads/FavoriteButton', () => ({ FavoriteButton: () => <button type="button">Save</button> }));
vi.mock('@/components/search/SaveSearchButton', () => ({ SaveSearchButton: () => null }));
vi.mock('@/lib/api/ads', () => ({ listAds: vi.fn() }));
vi.mock('@/lib/queries/categories', () => ({ useCategoryTreeQuery: vi.fn(), useCategoryStatsQuery: vi.fn() }));
vi.mock('@/lib/queries/locations', () => ({ useQatarLocationsQuery: vi.fn() }));

import { listAds } from '@/lib/api/ads';
import { setClientLocale } from '@/lib/i18n/locale';
import { useCategoryStatsQuery, useCategoryTreeQuery } from '@/lib/queries/categories';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import type { AdSummary, CategoryNode, PaginatedResponse } from '@/lib/api/types';

import { AdsListing } from './AdsListing';

const cars = { id: 'cat-cars', slug: 'cars', name: { en: 'Cars', ar: 'سيارات' }, children: [] } as unknown as CategoryNode;

function lookup<T>(data: T | undefined, state: { isPending?: boolean; isError?: boolean } = {}) {
  return { data, isPending: state.isPending ?? data === undefined, isError: state.isError ?? false, refetch: vi.fn() } as never;
}

function page(total: number, lastPage = 1): PaginatedResponse<AdSummary> {
  return { data: [], meta: { current_page: 1, last_page: lastPage, per_page: 20, total } } as unknown as PaginatedResponse<AdSummary>;
}

function renderListing() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>;
  return render(<AdsListing heading={(selected) => ({ title: selected ? 'Cars' : 'All ads', breadcrumb: [] })} />, { wrapper });
}

describe('AdsListing', () => {
  beforeEach(() => {
    setClientLocale('en');
    vi.clearAllMocks();
    navigation.search = '';
    vi.mocked(useCategoryTreeQuery).mockReturnValue(lookup([cars]));
    vi.mocked(useQatarLocationsQuery).mockReturnValue(lookup([]));
    vi.mocked(useCategoryStatsQuery).mockReturnValue(lookup(undefined));
    vi.mocked(listAds).mockResolvedValue(page(3));
  });

  it('holds the feed until a category slug of the URL resolves, then filters by its id', async () => {
    navigation.search = 'category=cars';
    vi.mocked(useCategoryTreeQuery).mockReturnValue(lookup(undefined, { isPending: true }));
    const { rerender } = renderListing();

    expect(listAds).not.toHaveBeenCalled();

    vi.mocked(useCategoryTreeQuery).mockReturnValue(lookup([cars]));
    rerender(<AdsListing heading={(selected) => ({ title: selected ? 'Cars' : 'All ads', breadcrumb: [] })} />);

    await waitFor(() => expect(listAds).toHaveBeenCalledWith(expect.objectContaining({ category_id: 'cat-cars' })));
    expect(listAds).toHaveBeenCalledTimes(1);
    expect(listAds).not.toHaveBeenCalledWith(expect.objectContaining({ per_page: expect.anything() }));
  });

  it('shows the empty state with a reset, not the unfiltered feed, for an unknown slug', () => {
    navigation.search = 'category=boats';
    renderListing();

    expect(screen.getByText('No ads here yet.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Reset filters' })).toBeInTheDocument();
    expect(listAds).not.toHaveBeenCalled();
  });

  it('offers a retry instead of the feed when the category tree fails', () => {
    navigation.search = 'category=cars';
    vi.mocked(useCategoryTreeQuery).mockReturnValue(lookup(undefined, { isPending: false, isError: true }));
    renderListing();

    expect(screen.getByText('We couldn’t load the ads. Please try again.').closest('[role="alert"]')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument();
    expect(listAds).not.toHaveBeenCalled();
  });

  it('announces how many ads matched once the feed settles', async () => {
    renderListing();

    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('3 results'));
  });

  it('never links past the last page the feed serves', async () => {
    vi.mocked(listAds).mockResolvedValue(page(20_000, 1_000));
    renderListing();

    expect(await screen.findByRole('link', { name: 'Last page' })).toHaveAttribute('href', '/ads?page=250');
  });
});
