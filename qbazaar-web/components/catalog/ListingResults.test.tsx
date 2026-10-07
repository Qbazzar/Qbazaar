import type { ImgHTMLAttributes } from 'react';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/image', () => ({
  default: ({ fill, ...props }: ImgHTMLAttributes<HTMLImageElement> & { fill?: boolean }) => <img data-fill={fill} {...props} />,
}));
vi.mock('@/components/ads/FavoriteButton', () => ({
  FavoriteButton: ({ adId }: { adId: string }) => <button type="button">{`Save ${adId}`}</button>,
}));

import { setClientLocale } from '@/lib/i18n/locale';
import type { AdSummary } from '@/lib/api/types';
import { useLocationsStore } from '@/store/locations';

import { ListingResults } from './ListingResults';

function ad(id: string, overrides: Partial<AdSummary> = {}): AdSummary {
  return {
    id,
    title: `Ad ${id}`,
    price: 2350,
    price_type: 'fixed',
    currency: 'QAR',
    status: 'active',
    views_count: 0,
    favorites_count: 0,
    primary_image: null,
    location_slug: 'al-wakrah',
    category_slug: 'cars',
    published_at: null,
    created_at: '2026-10-01T10:00:00Z',
    ...overrides,
  };
}

const ads = [ad('1'), ad('2', { location_slug: 'souq-waqif-area' })];

describe('ListingResults', () => {
  beforeEach(() => {
    setClientLocale('en');
    useLocationsStore.setState({
      qatar: [{ id: 'l1', parent_id: null, slug: 'al-wakrah', name: { en: 'Al Wakrah', ar: 'الوكرة' }, type: 'city', lat: null, lng: null, children: [] }],
      hydrated: true,
    });
  });

  it('renders each ad as the grid card under 1001 px and the list card from there, for CSS to pick one', () => {
    render(<ListingResults ads={ads} view="list" isLoading={false} empty={null} label="Cars" />);
    const list = screen.getByRole('list', { name: 'Cars' });
    const links = screen.getAllByRole('link', { name: 'Ad 1' });

    expect(list.children).toHaveLength(2);
    expect(links).toHaveLength(2);
    expect(links[0].closest('article')).toHaveClass('qb-desktop:hidden');
    expect(links[1].closest('article')).toHaveClass('hidden', 'qb-desktop:flex');
    expect(list).toHaveClass('qb-tablet:grid-cols-2', 'qb-desktop:flex-col');
  });

  it('renders one grid card per ad in the grid view', () => {
    render(<ListingResults ads={ads} view="grid" isLoading={false} empty={null} label="Cars" />);

    expect(screen.getAllByRole('link', { name: 'Ad 1' })).toHaveLength(1);
    expect(screen.getByRole('list', { name: 'Cars' })).toHaveClass('qb-desktop:grid-cols-3');
  });

  it('names places from the location tree, or from the slug before it loads', () => {
    render(<ListingResults ads={ads} view="grid" isLoading={false} empty={null} label="Cars" />);

    expect(screen.getByText('Al Wakrah')).toBeInTheDocument();
    expect(screen.getByText('souq waqif area')).toBeInTheDocument();
  });

  it('marks the list busy while the next page loads', () => {
    render(<ListingResults ads={ads} view="grid" isLoading={false} isFetching empty={null} label="Cars" />);

    expect(screen.getByRole('list', { name: 'Cars' })).toHaveAttribute('aria-busy', 'true');
  });

  it('shows placeholders while loading and the empty state without ads', () => {
    const { rerender } = render(<ListingResults ads={[]} view="list" isLoading empty={<p>Nothing here</p>} label="Cars" />);
    expect(screen.getByText('Loading…').parentElement).toHaveAttribute('aria-busy', 'true');

    rerender(<ListingResults ads={[]} view="list" isLoading={false} empty={<p>Nothing here</p>} label="Cars" />);
    expect(screen.getByText('Nothing here')).toBeInTheDocument();
  });
});
