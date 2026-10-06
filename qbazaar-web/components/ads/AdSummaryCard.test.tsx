import type { ImgHTMLAttributes } from 'react';
import { act, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/image', () => ({
  default: ({ fill, ...props }: ImgHTMLAttributes<HTMLImageElement> & { fill?: boolean }) => <img data-fill={fill} {...props} />,
}));
vi.mock('@/components/ads/FavoriteButton', () => ({
  FavoriteButton: () => <button type="button">Save</button>,
}));

import { setClientLocale } from '@/lib/i18n/locale';
import type { AdSummary, Location, Media } from '@/lib/api/types';
import { useLocationsStore } from '@/store/locations';

import { AdSummaryCard } from './AdSummaryCard';

const image: Media = {
  id: 'm1',
  collection: 'images',
  url: '',
  sizes: { thumbnail: '/t.jpg', medium: 'https://cdn.qbazaar.qa/1/medium.jpg', large: '/l.jpg', original_webp: '/o.webp' },
  blurhash: null,
  width: 1024,
  height: 768,
  order: 1,
  size_bytes: 1,
};

const ad: AdSummary = {
  id: '01abc',
  title: 'Toyota Land Cruiser',
  price: 285000,
  price_type: 'fixed',
  currency: 'QAR',
  status: 'active',
  views_count: 0,
  favorites_count: 0,
  primary_image: image,
  location_slug: 'al-wakrah',
  category_slug: 'cars',
  published_at: null,
  created_at: '2026-10-01T10:00:00Z',
};

const wakrah: Location = { id: 'l1', parent_id: null, slug: 'al-wakrah', name: { en: 'Al Wakrah', ar: 'الوكرة' }, type: 'city', lat: null, lng: null, children: [] };

describe('AdSummaryCard', () => {
  beforeEach(() => {
    setClientLocale('en');
    useLocationsStore.setState({ qatar: null, hydrated: false });
  });

  it('links the card to the ad and shows its price on the photo', () => {
    const { container } = render(<AdSummaryCard ad={ad} />);

    expect(screen.getByRole('link', { name: 'Toyota Land Cruiser' })).toHaveAttribute('href', '/ads/01abc');
    expect(screen.getByText('QAR 285,000')).toHaveClass('bg-qb-brand');
    expect(container.querySelector('img')).toHaveAttribute('src', 'https://cdn.qbazaar.qa/1/medium.jpg');
    expect(container.querySelector('img')).toHaveAttribute('alt', '');
  });

  it('names the place from the location tree, or from the slug before it loads', () => {
    render(<AdSummaryCard ad={ad} />);
    expect(screen.getByText('al wakrah')).toBeInTheDocument();

    act(() => useLocationsStore.getState().setQatar([wakrah]));
    expect(screen.getByText('Al Wakrah')).toBeInTheDocument();
  });

  it('marks promoted ads as "Top Ad" in the list layout', () => {
    render(<AdSummaryCard ad={{ ...ad, promotion: 'premium' }} layout="list" />);

    expect(screen.getByText('Top Ad')).toHaveClass('bg-qb-brand');
    expect(screen.getByText('QAR 285,000')).not.toHaveClass('bg-qb-brand');
  });

  it('labels a missing photo', () => {
    render(<AdSummaryCard ad={{ ...ad, primary_image: null }} />);

    expect(screen.getByText('No image')).toBeInTheDocument();
  });
});
