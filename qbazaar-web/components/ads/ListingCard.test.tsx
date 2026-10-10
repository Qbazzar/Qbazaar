import type { ImgHTMLAttributes } from 'react';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/image', () => ({
  default: ({ fill, ...props }: ImgHTMLAttributes<HTMLImageElement> & { fill?: boolean }) => <img data-fill={fill} {...props} />,
}));
vi.mock('@/components/ads/FavoriteButton', () => ({
  FavoriteButton: ({ initialFavorited }: { initialFavorited?: boolean }) => (
    <button type="button" aria-pressed={Boolean(initialFavorited)}>
      Save
    </button>
  ),
}));

import { setClientLocale } from '@/lib/i18n/locale';
import type { AdSummary } from '@/lib/api/types';

import { ListingCard } from './ListingCard';

const ad: AdSummary = {
  id: '01abc',
  title: 'Toyota Land Cruiser',
  price: 285000,
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
  spec_chips: [{ key: 'year', label: 'Year', value: '2022' }],
};

beforeEach(() => setClientLocale('en'));

describe('ListingCard', () => {
  it('links to the ad with the price on the photo', () => {
    render(<ListingCard ad={ad} variant="related" />);

    expect(screen.getByRole('link', { name: 'Toyota Land Cruiser' })).toHaveAttribute('href', '/ads/01abc');
    expect(screen.getByText('QAR 285,000')).toHaveClass('bg-qb-brand');
  });

  it('has no heart and no spec chips under "Another Ads From Seller"', () => {
    render(<ListingCard ad={ad} variant="related" />);

    expect(screen.queryByRole('button', { name: 'Save' })).toBeNull();
    expect(screen.queryByText('2022')).toBeNull();
  });

  it('starts the wishlist heart filled', () => {
    render(<ListingCard ad={ad} variant="saved" favorited />);

    expect(screen.getByRole('button', { name: 'Save' })).toHaveAttribute('aria-pressed', 'true');
  });

  it('shows the spec chips on a company card', () => {
    render(<ListingCard ad={ad} variant="company" />);

    expect(screen.getByText('2022')).toBeInTheDocument();
  });
});
