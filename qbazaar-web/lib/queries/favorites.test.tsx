import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api/favorites', () => ({
  listFavorites: vi.fn(),
  toggleFavorite: vi.fn(),
  listFavoriteIds: vi.fn(),
  removeFavorite: vi.fn(),
}));

import { listFavoriteIds, removeFavorite } from '@/lib/api/favorites';
import { useFavoritesStore } from '@/store/favorites';

import { useClearFavoritesMutation } from './favorites';

function wrapper({ children }: { children: ReactNode }) {
  return <QueryClientProvider client={new QueryClient({ defaultOptions: { mutations: { retry: false } } })}>{children}</QueryClientProvider>;
}

beforeEach(() => {
  vi.clearAllMocks();
  useFavoritesStore.getState().setIds(['a', 'b']);
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
