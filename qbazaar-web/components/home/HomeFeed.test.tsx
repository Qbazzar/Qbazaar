import type { ReactElement } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render as renderDom, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { HomeFeed, HomeSeller } from '@/lib/api/home';
import type { AdSummary, Category } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';

const feed = vi.hoisted(() => ({ data: undefined as HomeFeed | undefined, isLoading: false, isError: false, refetch: vi.fn() }));
vi.mock('@/lib/queries/home', () => ({ useHomeFeedQuery: () => feed }));
vi.mock('next/navigation', () => ({ usePathname: () => '/', useRouter: () => ({ push: vi.fn() }) }));

import { HomeCategoryStrip } from './HomeCategoryStrip';
import { HomeFeaturedCompanies } from './HomeFeaturedCompanies';
import { HomeFeedAds } from './HomeFeedAds';

function ad(id: string, title: string, chips: string[] = []): AdSummary {
  return {
    id,
    title,
    price: 2350,
    price_type: 'fixed',
    currency: 'QAR',
    status: 'active',
    views_count: 0,
    favorites_count: 0,
    primary_image: null,
    location_slug: 'doha',
    category_slug: 'electronics',
    published_at: null,
    created_at: '2026-10-06T07:00:00Z',
    spec_chips: chips.map((value, index) => ({ key: `field-${index}`, label: `Field ${index}`, value })),
  };
}

function seller(id: string, name: string, overrides: Partial<HomeSeller> = {}): HomeSeller {
  return { id, full_name: name, avatar_url: null, account_type: 'business', ads_count: 9, rating_avg: 0, rating_count: 0, ...overrides };
}

function category(index: number): Category {
  return {
    id: `c${index}`,
    slug: `category-${index}`,
    name: { en: `Category ${index}`, ar: `القسم ${index}` },
    icon: 'car',
    ads_count: 1250,
  } as Category;
}

const homeFeed: HomeFeed = {
  categories: Array.from({ length: 10 }, (_, i) => category(i)),
  recommended: [ad('ad-1', 'Apple Watch Ultra 2', ['Used', 'Black'])],
  featured_sellers: Array.from({ length: 7 }, (_, i) =>
    seller(`s${i}`, `Seller ${i}`, i === 0 ? { location: { slug: 'al-wakra', name: { en: 'Al Wakra', ar: 'الوكرة' } } } : {}),
  ),
  best_selling: [ad('ad-2', 'Toyota Corolla')],
};

// The favourite buttons on the cards need a query client.
function render(ui: ReactElement) {
  const client = new QueryClient();
  return renderDom(ui, { wrapper: ({ children }) => <QueryClientProvider client={client}>{children}</QueryClientProvider> });
}

describe('home feed sections', () => {
  beforeEach(() => {
    setClientLocale('en');
    Object.assign(feed, { data: homeFeed, isLoading: false, isError: false });
    feed.refetch.mockClear();
  });

  it('shows the first eight categories as tiles with their ad counts', () => {
    render(<HomeCategoryStrip />);

    const tiles = screen.getAllByRole('link');
    expect(tiles).toHaveLength(8);
    expect(tiles[0]).toHaveAttribute('href', '/c/category-0');
    expect(within(tiles[0]).getByText('Category 0')).toBeInTheDocument();
    expect(within(tiles[0]).getByText('1,250 ads')).toBeInTheDocument();
  });

  it('announces a failed feed and retries it', async () => {
    Object.assign(feed, { data: undefined, isError: true });
    render(<HomeCategoryStrip />);

    expect(screen.getByRole('alert')).toHaveTextContent('Something went wrong, please retry');
    await userEvent.click(screen.getByRole('button', { name: 'Retry' }));
    expect(feed.refetch).toHaveBeenCalledOnce();
  });

  it('shows the chosen ad list with its spec chips', () => {
    render(<HomeFeedAds list="recommended" id="home-recommended" title="Recommended for you" subtitle="Hand-picked deals" />);

    const rail = screen.getByRole('list', { name: 'Recommended for you' });
    expect(within(rail).getByRole('link', { name: 'Apple Watch Ultra 2' })).toHaveAttribute('href', '/ads/ad-1');
    expect(within(rail).getByText('Used')).toBeInTheDocument();
    expect(within(rail).getByText('Black')).toBeInTheDocument();
    expect(screen.queryByText('Toyota Corolla')).toBeNull();
  });

  it('hides an ad list the feed has no ads for, or failed to load', () => {
    feed.data = { ...homeFeed, best_selling: [] };
    const { container, rerender } = render(<HomeFeedAds list="best_selling" id="b" title="Best Selling" subtitle="Trending" />);
    expect(container).toBeEmptyDOMElement();

    Object.assign(feed, { data: undefined, isError: true });
    rerender(<HomeFeedAds list="recommended" id="r" title="Recommended" subtitle="Deals" />);
    expect(container).toBeEmptyDOMElement();
  });

  it('shows the first five featured sellers, each linking to its profile', () => {
    render(<HomeFeaturedCompanies />);

    const cards = screen.getAllByRole('listitem');
    expect(cards).toHaveLength(5);
    expect(screen.getByRole('link', { name: 'Seller 0' })).toHaveAttribute('href', '/u/s0');
    expect(screen.getByText('Al Wakra')).toBeInTheDocument();
    expect(screen.getAllByText('9 ads')).toHaveLength(5);
    expect(screen.getByRole('link', { name: /View All/ })).toHaveAttribute('href', '/companies');
  });

  it('marks the companies as loading, and drops the section without sellers', () => {
    Object.assign(feed, { data: undefined, isLoading: true });
    const { container, rerender } = render(<HomeFeaturedCompanies />);
    expect(container.querySelector('[aria-busy="true"]')).not.toBeNull();

    Object.assign(feed, { data: { ...homeFeed, featured_sellers: [] }, isLoading: false });
    rerender(<HomeFeaturedCompanies />);
    expect(container).toBeEmptyDOMElement();
  });
});
