import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api/favorites', () => ({ setFavoriteCategory: vi.fn(), listFavoriteCategories: vi.fn() }));
vi.mock('@/lib/api/categories', () => ({ getCategoryPage: vi.fn() }));

import { getCategoryPage } from '@/lib/api/categories';
import { setFavoriteCategory } from '@/lib/api/favorites';
import { useAuthStore } from '@/store/auth';
import type { CategoryPage, User } from '@/lib/api/types';

import { categoryKeys, useCategoryPageQuery } from './categories';
import { useSetFavoriteCategoryMutation } from './favorite-categories';

const page = (isFavorited: boolean) => ({ is_favorited: isFavorited }) as CategoryPage;

function setup() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>;
  return { client, wrapper };
}

beforeEach(() => {
  vi.clearAllMocks();
  useAuthStore.setState({ user: { id: 'u1' } as User, accessToken: 't' });
});

describe('useSetFavoriteCategoryMutation', () => {
  it('writes the flag into the cached page at once and keeps the server answer', async () => {
    const { client, wrapper } = setup();
    client.setQueryData(categoryKeys.page('cars', 'u1'), page(false));
    vi.mocked(setFavoriteCategory).mockResolvedValue({ favorited: true });
    const { result } = renderHook(() => useSetFavoriteCategoryMutation(), { wrapper });

    await act(() => result.current.mutateAsync({ slug: 'cars', favorited: true }));

    expect(client.getQueryData<CategoryPage>(categoryKeys.page('cars', 'u1'))?.is_favorited).toBe(true);
  });

  it('puts the previous page back when the request fails', async () => {
    const { client, wrapper } = setup();
    client.setQueryData(categoryKeys.page('cars', 'u1'), page(false));
    vi.mocked(setFavoriteCategory).mockRejectedValue(new Error('FAV_002'));
    const { result } = renderHook(() => useSetFavoriteCategoryMutation(), { wrapper });

    await act(() => result.current.mutateAsync({ slug: 'cars', favorited: true }).catch(() => undefined));

    expect(client.getQueryData<CategoryPage>(categoryKeys.page('cars', 'u1'))?.is_favorited).toBe(false);
  });
});

describe('useCategoryPageQuery', () => {
  it('does not show the page a guest cached to the account that logs in afterwards', async () => {
    const { client, wrapper } = setup();
    useAuthStore.setState({ user: null, accessToken: null });
    vi.mocked(getCategoryPage).mockResolvedValue(page(false));
    const guest = renderHook(() => useCategoryPageQuery('cars'), { wrapper });
    await waitFor(() => expect(guest.result.current.data?.is_favorited).toBe(false));
    guest.unmount();

    useAuthStore.setState({ user: { id: 'u1' } as User, accessToken: 't' });
    vi.mocked(getCategoryPage).mockResolvedValue(page(true));
    const member = renderHook(() => useCategoryPageQuery('cars'), { wrapper });

    await waitFor(() => expect(member.result.current.data?.is_favorited).toBe(true));
    expect(getCategoryPage).toHaveBeenCalledTimes(2);
    expect(client.getQueryData<CategoryPage>(categoryKeys.page('cars', 'guest'))?.is_favorited).toBe(false);
  });
});
