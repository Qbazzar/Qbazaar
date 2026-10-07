import type { ImgHTMLAttributes } from 'react';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/image', () => ({
  default: ({ fill, ...props }: ImgHTMLAttributes<HTMLImageElement> & { fill?: boolean }) => <img data-fill={fill} {...props} />,
}));
vi.mock('@/components/ads/FavoriteButton', () => ({ FavoriteButton: () => <button type="button">Save</button> }));

import { setClientLocale } from '@/lib/i18n/locale';
import type { AdSummary, Category, CategorySection } from '@/lib/api/types';

import { CategoryHub } from './CategoryHub';

function category(slug: string, name: string): Category {
  return {
    id: slug,
    parent_id: 'vehicles',
    slug,
    name: { en: name, ar: name },
    description: null,
    icon: null,
    order: 0,
    is_active: true,
    custom_fields: null,
    custom_filters: null,
    ads_count: 12,
    today_count: 0,
    created_at: '',
    updated_at: '',
  };
}

function ad(id: string): AdSummary {
  return {
    id,
    title: `Ad ${id}`,
    price: 1000,
    price_type: 'fixed',
    currency: 'QAR',
    status: 'active',
    views_count: 0,
    favorites_count: 0,
    primary_image: null,
    location_slug: 'doha',
    category_slug: 'cars',
    published_at: null,
    created_at: '',
  };
}

const section = (slug: string, name: string, ads: AdSummary[]): CategorySection => ({ category: category(slug, name), ads });

describe('CategoryHub', () => {
  beforeEach(() => setClientLocale('en'));

  it('shows a row per sub-category with ads and the tiles after the first two rows', () => {
    const sections = [
      section('cars', 'Cars', [ad('1'), ad('2'), ad('3'), ad('4')]),
      section('boats', 'Boats', []),
      section('motorcycles', 'Motorcycles', [ad('5')]),
      section('parts', 'Auto Parts', [ad('6')]),
    ];
    render(<CategoryHub sections={sections} isLoading={false} />);
    const headings = screen.getAllByRole('heading', { level: 2 }).map((heading) => heading.textContent);

    expect(headings).toEqual(['Cars', 'Motorcycles', 'Categories', 'Auto Parts']);
    expect(screen.getAllByRole('link', { name: /View All/ })[0]).toHaveAttribute('href', '/c/cars');

    const cars = within(screen.getByRole('list', { name: 'Cars' })).getAllByRole('listitem');
    expect(cars).toHaveLength(4);
    expect(cars[3]).toHaveClass('qb-desktop:hidden');
  });

  it('lists six tiles and reveals the rest on "View More"', async () => {
    const sections = Array.from({ length: 8 }, (_, i) => section(`sub-${i}`, `Sub ${i}`, []));
    render(<CategoryHub sections={sections} isLoading={false} />);
    const more = screen.getByRole('button', { name: 'View More' });

    expect(screen.getAllByRole('link', { name: /^Sub/ })).toHaveLength(6);
    expect(more).toHaveAttribute('aria-expanded', 'false');

    await userEvent.setup().click(more);
    expect(screen.getAllByRole('link', { name: /^Sub/ })).toHaveLength(8);
    expect(screen.getByRole('button', { name: 'View Less' })).toHaveAttribute('aria-expanded', 'true');
  });

  it('says so when no sub-category has ads yet', () => {
    render(<CategoryHub sections={[section('cars', 'Cars', [])]} isLoading={false} />);

    expect(screen.getByRole('heading', { name: 'No ads in this category yet.' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Cars/ })).toHaveAttribute('href', '/c/cars');
  });

  it('keeps its frame while loading', () => {
    render(<CategoryHub sections={undefined} isLoading />);

    expect(screen.getByText('Loading…').parentElement).toHaveAttribute('aria-busy', 'true');
  });
});
