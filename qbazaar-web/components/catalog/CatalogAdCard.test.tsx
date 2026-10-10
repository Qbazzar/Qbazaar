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

import { CatalogAdCard } from './CatalogAdCard';

const ad: AdSummary = {
  id: '01abc',
  title: 'BMW 320d Touring',
  summary: '24/7 plumber available now inside Doha for a small fee.',
  price: 287000,
  price_type: 'fixed',
  currency: 'QAR',
  status: 'active',
  views_count: 0,
  favorites_count: 0,
  primary_image: null,
  location_slug: 'doha',
  category_slug: 'cars',
  published_at: null,
  created_at: '2026-10-01T10:00:00Z',
  spec_chips: [
    { key: 'year', label: 'Year', value: '2019' },
    { key: 'fuel', label: 'Fuel', value: 'Diesel' },
  ],
  promotion: 'premium',
};

describe('CatalogAdCard', () => {
  beforeEach(() => setClientLocale('en'));

  it('lets Latin titles and descriptions find their own direction inside an Arabic page', () => {
    render(<CatalogAdCard ad={ad} layout="row" />);

    expect(screen.getByRole('heading', { name: 'BMW 320d Touring' })).toHaveAttribute('dir', 'auto');
    expect(screen.getByText(ad.summary as string)).toHaveAttribute('dir', 'auto');
  });

  it('puts the price on the phone photo, nothing on the tablet photo and "Top Ad" on the desktop photo', () => {
    render(<CatalogAdCard ad={ad} layout="row" />);
    const prices = screen.getAllByText('QAR 287,000');
    const besideTitle = prices.find((price) => price.parentElement?.querySelector('h3'));
    const onPhoto = prices.find((price) => price !== besideTitle);

    expect(onPhoto).toHaveClass('inline-flex', 'qb-tablet:hidden', 'text-qb-micro', 'font-medium');
    expect(besideTitle).toHaveClass('hidden', 'qb-tablet:block', 'text-qb-h4', 'font-semibold');
    expect(screen.getByText('Top Ad')).toHaveClass('hidden', 'qb-desktop:inline-flex');
  });

  it('sets the list text as category.html does at every width', () => {
    render(<CatalogAdCard ad={ad} layout="row" />);

    expect(screen.getByRole('heading', { level: 3 })).toHaveClass('text-qb-h4', 'font-semibold', 'text-qb-ink');
    expect(screen.getByText(ad.summary as string)).toHaveClass('text-qb-body-sm', 'text-qb-ink-faint');
    expect(screen.getByText('doha').closest('p')).toHaveClass('text-qb-caption', 'text-qb-ink-subtle');
    expect(screen.getByText('2019')).toHaveClass('text-qb-label', 'text-qb-ink-faint');
  });

  it('gives the grid card a two-line description and the overview card none', () => {
    const { rerender } = render(<CatalogAdCard ad={ad} layout="grid" />);
    expect(screen.getByText(ad.summary as string)).toHaveClass('line-clamp-2', 'text-qb-label', 'text-qb-ink-faint');
    expect(screen.getByRole('heading', { level: 3 })).toHaveClass('text-qb-body', 'font-medium', 'text-qb-ink-title');
    expect(screen.getByText('QAR 287,000')).toHaveClass('text-qb-micro', 'font-medium');
    expect(screen.queryByText('Top Ad')).not.toBeInTheDocument();

    rerender(<CatalogAdCard ad={ad} layout="tile" />);
    expect(screen.queryByText(ad.summary as string)).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 3 })).toHaveClass('line-clamp-2', 'text-qb-body-sm', 'text-qb-ink-title');
  });

  it('keeps the favourite after the card link, as its own control', () => {
    render(<CatalogAdCard ad={ad} layout="tile" />);
    const link = screen.getByRole('link', { name: 'BMW 320d Touring' });
    const favorite = screen.getByRole('button', { name: 'Save 01abc' });

    expect(link).toHaveAttribute('href', '/ads/01abc');
    expect(link.compareDocumentPosition(favorite) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(link.contains(favorite)).toBe(false);
  });
});
