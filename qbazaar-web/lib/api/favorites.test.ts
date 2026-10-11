import { describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api/client', () => ({ api: { get: vi.fn(), put: vi.fn(), delete: vi.fn() } }));

import { api } from '@/lib/api/client';
import { listFavoriteCategories, listFavoriteIds, setFavoriteCategory } from '@/lib/api/favorites';

describe('listFavoriteIds', () => {
  it('reads the ids from the success envelope', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { success: true, data: { ids: ['ad-1', 'ad-2'] } } } as never);

    await expect(listFavoriteIds()).resolves.toEqual(['ad-1', 'ad-2']);
    expect(api.get).toHaveBeenCalledWith('/api/v1/account/favorites/ids');
  });
});

describe('favourite categories', () => {
  it('lists them from the success envelope', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { success: true, data: [{ slug: 'cars' }] } } as never);

    await expect(listFavoriteCategories()).resolves.toEqual([{ slug: 'cars' }]);
    expect(api.get).toHaveBeenCalledWith('/api/v1/account/favorite-categories');
  });

  it('adds with PUT and removes with DELETE, by slug', async () => {
    vi.mocked(api.put).mockResolvedValue({ data: { success: true, data: { favorited: true } } } as never);
    vi.mocked(api.delete).mockResolvedValue({ data: { success: true, data: { favorited: false } } } as never);

    await expect(setFavoriteCategory('cars & vans', true)).resolves.toEqual({ favorited: true });
    await expect(setFavoriteCategory('cars', false)).resolves.toEqual({ favorited: false });
    expect(api.put).toHaveBeenCalledWith('/api/v1/account/favorite-categories/cars%20%26%20vans');
    expect(api.delete).toHaveBeenCalledWith('/api/v1/account/favorite-categories/cars');
  });
});
