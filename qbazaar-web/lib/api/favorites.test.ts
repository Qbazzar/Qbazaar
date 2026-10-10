import { describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api/client', () => ({ api: { get: vi.fn() } }));

import { api } from '@/lib/api/client';
import { listFavoriteIds } from '@/lib/api/favorites';

describe('listFavoriteIds', () => {
  it('reads the ids from the success envelope', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { success: true, data: { ids: ['ad-1', 'ad-2'] } } } as never);

    await expect(listFavoriteIds()).resolves.toEqual(['ad-1', 'ad-2']);
    expect(api.get).toHaveBeenCalledWith('/api/v1/account/favorites/ids');
  });
});
