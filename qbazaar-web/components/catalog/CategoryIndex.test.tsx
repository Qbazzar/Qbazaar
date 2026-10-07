import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const replace = vi.fn();
vi.mock('next/navigation', () => ({
  usePathname: () => '/categories',
  useRouter: () => ({ replace }),
}));
vi.mock('@/lib/queries/categories', () => ({
  useMainCategoriesQuery: vi.fn(),
}));

import { setClientLocale } from '@/lib/i18n/locale';
import { useMainCategoriesQuery } from '@/lib/queries/categories';
import type { Category } from '@/lib/api/types';

import { CategoryIndex, CategoryIndexStats, filterCategories } from './CategoryIndex';

function category(slug: string, en: string, ar: string, extra: Partial<Category> = {}): Category {
  return {
    id: slug,
    parent_id: null,
    slug,
    name: { en, ar },
    description: null,
    icon: 'Car',
    order: 0,
    is_active: true,
    custom_fields: null,
    custom_filters: null,
    ads_count: 1200,
    today_count: 0,
    created_at: '',
    updated_at: '',
    ...extra,
  };
}

const categories = [
  category('vehicles', 'Vehicles', 'مركبات', { today_count: 3 }),
  category('real-estate', 'Real Estate', 'عقارات', { today_count: 1 }),
  category('pets', 'Pets', 'حيوانات أليفة'),
];

function mockQuery(data: Category[] | undefined, state: Partial<{ isLoading: boolean; isError: boolean }> = {}) {
  vi.mocked(useMainCategoriesQuery).mockReturnValue({ data, isLoading: false, isError: false, refetch: vi.fn(), ...state } as never);
}

describe('filterCategories', () => {
  it('matches the name in either language, ignoring case and spaces', () => {
    expect(filterCategories(categories, '  real ').map((c) => c.slug)).toEqual(['real-estate']);
    expect(filterCategories(categories, 'حيوانات').map((c) => c.slug)).toEqual(['pets']);
    expect(filterCategories(categories, '')).toHaveLength(3);
  });
});

describe('CategoryIndex', () => {
  beforeEach(() => {
    setClientLocale('en');
    replace.mockClear();
  });

  it('links every category card to its page with its ad count', () => {
    mockQuery(categories);
    render(<CategoryIndex page={1} />);

    expect(screen.getByRole('link', { name: /Vehicles/ })).toHaveAttribute('href', '/c/vehicles');
    expect(screen.getAllByText('1,200 Ads')).toHaveLength(3);
  });

  it('filters the cards while typing and says when nothing matches', async () => {
    mockQuery(categories);
    const user = userEvent.setup();
    render(<CategoryIndex page={1} />);
    const search = screen.getByRole('searchbox', { name: 'Search categories' });

    await user.type(search, 'pet');
    expect(screen.getAllByRole('link')).toHaveLength(1);

    await user.clear(search);
    await user.type(search, 'boats');
    expect(screen.getByRole('heading', { name: 'No matching categories' })).toBeInTheDocument();
  });

  it('paginates by 24 with crawlable links', () => {
    mockQuery(Array.from({ length: 30 }, (_, i) => category(`c${i}`, `Category ${i}`, `قسم ${i}`)));
    render(<CategoryIndex page={2} />);

    expect(screen.getAllByRole('link', { name: /^Category/ })).toHaveLength(6);
    expect(screen.getByRole('link', { name: 'Page 1' })).toHaveAttribute('href', '/categories');
    expect(screen.getByRole('link', { name: 'Page 2' })).toHaveAttribute('aria-current', 'page');
  });

  it('offers a retry when the categories fail to load', async () => {
    const refetch = vi.fn();
    vi.mocked(useMainCategoriesQuery).mockReturnValue({ data: undefined, isLoading: false, isError: true, refetch } as never);
    render(<CategoryIndex page={1} />);

    await userEvent.setup().click(screen.getByRole('button', { name: 'Retry' }));
    expect(refetch).toHaveBeenCalled();
  });
});

describe('CategoryIndexStats', () => {
  beforeEach(() => setClientLocale('en'));

  it('counts the categories and sums today\'s ads', () => {
    mockQuery(categories);
    const { container } = render(<CategoryIndexStats />);

    expect(container).toHaveTextContent('3 Categories•+4 Ads Today');
  });
});
