import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api/favorites', () => ({
  listFavorites: vi.fn(),
  toggleFavorite: vi.fn(),
  listFavoriteIds: vi.fn(),
  removeFavorite: vi.fn(),
}));

import { listFavoriteIds, listFavorites, removeFavorite, toggleFavorite } from '@/lib/api/favorites';
import { useAuthStore } from '@/store/auth';
import { useFavoritesStore } from '@/store/favorites';
import type { User } from '@/lib/api/types';

import { adKeys } from './ads';
import { useClearFavoritesMutation, useFavoritesQuery, useSyncAdFavorite, useToggleFavoriteMutation } from './favorites';

function newClient() {
  return new QueryClient({ defaultOptions: { mutations: { retry: false } } });
}

function wrapperFor(client: QueryClient) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
  };
}

function wrapper({ children }: { children: ReactNode }) {
  return <QueryClientProvider client={newClient()}>{children}</QueryClientProvider>;
}

beforeEach(() => {
  vi.clearAllMocks();
  useFavoritesStore.getState().setIds(['a', 'b']);
});

describe('useFavoritesQuery', () => {
  it('adds the ids of page 1 without dropping hearts already known', async () => {
    useAuthStore.setState({ user: { id: 'u' } as User, accessToken: 't' });
    vi.mocked(listFavorites).mockResolvedValue({ data: [{ id: 'c' }] } as never);
    renderHook(() => useFavoritesQuery({ page: 1 }), { wrapper });

    await waitFor(() => expect(useFavoritesStore.getState().ids.has('c')).toBe(true));
    expect([...useFavoritesStore.getState().ids].sort()).toEqual(['a', 'b', 'c']);
  });
});

describe('useClearFavoritesMutation', () => {
  it('removes every saved ad and empties the hearts', async () => {
    const ids = Array.from({ length: 9 }, (_, index) => `ad-${index}`);
    vi.mocked(listFavoriteIds).mockResolvedValue(ids);
    vi.mocked(removeFavorite).mockResolvedValue();
    const { result } = renderHook(() => useClearFavoritesMutation(), { wrapper });

    await act(() => result.current.mutateAsync());

    expect(vi.mocked(removeFavorite).mock.calls.map(([id]) => id)).toEqual(ids);
    expect(useFavoritesStore.getState().ids.size).toBe(0);
  });

  it('keeps the hearts when a removal fails', async () => {
    vi.mocked(listFavoriteIds).mockResolvedValue(['a', 'b']);
    vi.mocked(removeFavorite).mockRejectedValueOnce(new Error('429'));
    const { result } = renderHook(() => useClearFavoritesMutation(), { wrapper });

    await act(() => result.current.mutateAsync().catch(() => undefined));

    expect(useFavoritesStore.getState().ids.size).toBe(2);
  });
});

describe('useSyncAdFavorite', () => {
  it("lays the viewer's saved state over the hearts once it is known", () => {
    useFavoritesStore.getState().setIds([]);
    const { rerender } = renderHook(({ favorited }: { favorited?: boolean }) => useSyncAdFavorite('ad-1', favorited), {
      initialProps: {},
    });
    expect(useFavoritesStore.getState().ids.has('ad-1')).toBe(false);

    rerender({ favorited: true });
    expect(useFavoritesStore.getState().ids.has('ad-1')).toBe(true);

    rerender({ favorited: false });
    expect(useFavoritesStore.getState().ids.has('ad-1')).toBe(false);
  });

  it("leaves the hearts alone while the copy is not the viewer's", () => {
    renderHook(() => useSyncAdFavorite('a', undefined));

    expect(useFavoritesStore.getState().ids.has('a')).toBe(true);
  });
});

describe('useToggleFavoriteMutation', () => {
  it('keeps a cached ad page in step with the toggle', async () => {
    const client = newClient();
    client.setQueryData(adKeys.detail('a'), { id: 'a', is_favorited: true });
    vi.mocked(toggleFavorite).mockResolvedValue({ favorited: false, count: 0 });
    const { result } = renderHook(() => useToggleFavoriteMutation(), { wrapper: wrapperFor(client) });

    await act(() => result.current.mutateAsync('a'));

    expect(client.getQueryData(adKeys.detail('a'))).toEqual({ id: 'a', is_favorited: false });
    expect(useFavoritesStore.getState().ids.has('a')).toBe(false);
  });
});

describe('useClearFavoritesMutation and cached ad pages', () => {
  it('marks every cached ad page as not saved', async () => {
    const client = newClient();
    client.setQueryData(adKeys.detail('a'), { id: 'a', is_favorited: true });
    vi.mocked(listFavoriteIds).mockResolvedValue(['a']);
    vi.mocked(removeFavorite).mockResolvedValue();
    const { result } = renderHook(() => useClearFavoritesMutation(), { wrapper: wrapperFor(client) });

    await act(() => result.current.mutateAsync());

    expect(client.getQueryData(adKeys.detail('a'))).toEqual({ id: 'a', is_favorited: false });
  });
});
